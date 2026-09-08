<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use App\Services\SalesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShopWorkspaceController extends Controller
{
    private function shop(string $id, bool $write = false, bool $administration = false): Shop
    {
        $shop = backpack_user()->accessibleShops()->findOrFail($id);
        if ($write) {
            abort_unless(backpack_user()->manages($shop, $administration) && $shop->status !== 'frozen', 403);
        }

        return $shop;
    }

    public function index(): View
    {
        $shops = backpack_user()->accessibleShops()->withCount('products')->orderBy('name')->paginate(20);

        return view('admin.shops', compact('shops'));
    }

    public function show(Request $request, string $shop): View
    {
        $shop = $this->shop($shop);
        $search = mb_substr((string) $request->query('q', ''), 0, 100);
        $products = $shop->products()->when($search, fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('barcode', $search)))->orderBy('name')->paginate(30)->withQueryString();
        $members = $shop->members()->with('user')->get();
        if (backpack_user()->is_platform_admin) {
            $shop->load('owner');

            return view('admin.shop-moderation', compact('shop', 'products', 'members', 'search'));
        }
        $canManage = backpack_user()->manages($shop, true) && $shop->status !== 'frozen';
        $canSell = backpack_user()->manages($shop) && $shop->status === 'approved';
        $salesTotal = $shop->orders()->where('status', 'paid')->sum('total');
        $orders = $shop->orders()->latest()->limit(10)->get();

        return view('admin.workspace', compact('shop', 'products', 'members', 'canManage', 'canSell', 'salesTotal', 'orders', 'search'));
    }

    public function credentials(Request $request, string $shop): RedirectResponse
    {
        $shop = $this->shop($shop, true, true);
        $data = $request->validate([
            'public_key' => ['nullable', 'string', 'regex:/^pk_(test|live)_[a-zA-Z0-9]+$/', 'max:255'],
            'secret_key' => ['nullable', 'string', 'regex:/^sk_(test|live)_[a-zA-Z0-9]+$/', 'max:255'],
            'currency' => ['required', Rule::in(['GHS', 'NGN', 'ZAR', 'KES', 'XOF', 'EGP'])],
        ]);
        if ($shop->orders()->where('status', 'pending')->where('expires_at', '>', now())->exists() && $shop->currency !== $data['currency']) {
            throw ValidationException::withMessages(['currency' => 'Wait for pending checkouts to expire before changing currency.']);
        }
        $shop->currency = $data['currency'];
        if (! empty($data['public_key'])) {
            $shop->paystack_public_key = $data['public_key'];
        }
        if (! empty($data['secret_key'])) {
            $shop->paystack_secret_key = $data['secret_key'];
        }
        $shop->save();

        return back()->with('success', 'Payment settings saved securely.');
    }

    public function member(Request $request, string $shop): RedirectResponse
    {
        $shop = $this->shop($shop, true, true);
        $request->merge(['email' => Str::lower((string) $request->input('email'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:12', 'max:255'], 'role' => ['required', Rule::in(['admin', 'manager'])],
        ]);
        DB::transaction(function () use ($shop, $data) {
            $user = User::where('email', $data['email'])->first();
            if (! $user) {
                if (empty($data['password'])) {
                    throw ValidationException::withMessages(['password' => 'Provide an initial password of at least 12 characters for a new user.']);
                }
                $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            }
            if ($user->is_platform_admin || $user->id === $shop->owner_id) {
                throw ValidationException::withMessages(['email' => 'This account cannot be added as shop staff.']);
            }
            $shop->members()->updateOrCreate(['user_id' => $user->id], ['role' => $data['role']]);
        });

        return back()->with('success', 'Team member saved. Existing account passwords are unchanged.');
    }

    public function removeMember(string $shop, string $member): RedirectResponse
    {
        $shop = $this->shop($shop, true, true);
        $shop->members()->findOrFail($member)->delete();

        return back()->with('success', 'Team access removed.');
    }

    public function duplicate(string $product): RedirectResponse
    {
        $product = Product::whereIn('shop_id', backpack_user()->accessibleShops()->select('shops.id'))->findOrFail($product);
        $this->shop($product->shop_id, true);
        abort_if($product->status === 'frozen', 403);
        $copy = $product->replicate(['barcode', 'sku', 'image']);
        $copy->name = Str::limit($product->name, 140, '').' (copy)';
        $copy->quantity = $product->quantity === null ? null : 0;
        $copy->status = 'pending';
        $sourcePath = Str::start($product->image ?? '', 'products/');
        if ($product->image && Storage::disk('public')->exists($sourcePath)) {
            $path = 'products/'.Str::uuid().'.'.pathinfo($sourcePath, PATHINFO_EXTENSION);
            Storage::disk('public')->copy($sourcePath, $path);
            $copy->image = $path;
        }
        $copy->save();

        return redirect()->route('product.edit', $copy->id)->with('success', 'Product duplicated. Set its barcode and stock before approval.');
    }

    public function bulk(Request $request, string $shop): RedirectResponse
    {
        $shop = $this->shop($shop, true);
        $data = $request->validate(['csv' => ['required', 'string', 'max:200000']]);
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Could not open CSV input.');
        }
        fwrite($stream, $data['csv']);
        rewind($stream);
        $header = fgetcsv($stream, escape: '');
        $expected = ['name', 'description', 'cost_price', 'selling_price', 'quantity', 'barcode', 'sku'];
        if ($header !== $expected) {
            fclose($stream);
            throw ValidationException::withMessages(['csv' => 'Use the exact column header shown in the form.']);
        }
        $rows = [];
        while (($row = fgetcsv($stream, escape: '')) !== false) {
            if ($row === [null]) {
                continue;
            }
            if (count($row) !== count($header) || count($rows) >= 100) {
                fclose($stream);
                throw ValidationException::withMessages(['csv' => 'Each row needs seven columns; import at most 100 products at a time.']);
            }
            $rows[] = array_combine($header, array_map(fn ($value) => $value === '' ? null : $value, $row));
        }
        fclose($stream);
        if (! $rows) {
            throw ValidationException::withMessages(['csv' => 'Add at least one product row.']);
        }
        DB::transaction(function () use ($shop, $rows) {
            foreach ($rows as $index => $row) {
                $validator = Validator::make($row, ProductRequest::productRules($shop->id));
                if ($validator->fails()) {
                    throw ValidationException::withMessages(['csv' => 'Row '.($index + 2).': '.$validator->errors()->first()]);
                }
                $shop->products()->create($validator->validated());
            }
        });

        return back()->with('success', count($rows).' products created and submitted for approval.');
    }

    public function sale(Request $request, string $shop, SalesService $sales): RedirectResponse
    {
        $shop = $this->shop($shop, true);
        $data = $request->validate(CheckoutController::rules());
        $order = $sales->create($shop, $data, array_filter($data['items'], fn ($quantity) => $quantity > 0), backpack_user());

        return redirect()->route('workspace.receipt', $order);
    }

    public function receipt(Order $order): View
    {
        abort_if(backpack_user()->is_platform_admin, 403);
        $this->shop($order->shop_id);

        return view('admin.receipt', ['order' => $order->load('items', 'shop')]);
    }
}
