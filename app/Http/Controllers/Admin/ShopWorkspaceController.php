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
        if (backpack_user()->is_platform_admin) {
            $stats = [
                'shops' => Shop::count(),
                'pending_shops' => Shop::where('status', 'pending')->count(),
                'users' => User::count(),
                'products' => Product::count(),
            ];
            $shops = Shop::where('status', 'pending')
                ->withCount('products')
                ->orderBy('name')->orderBy('id')->paginate(20);

            return view('admin.overview', compact('stats', 'shops'));
        }

        $shops = backpack_user()->accessibleShops()->withCount('products')->orderBy('name')->paginate(20);

        return view('admin.shops', compact('shops'));
    }

    public function show(Request $request, string $shop): View
    {
        $shop = $this->shop($shop);
        $shop->load('owner');
        if (backpack_user()->is_platform_admin) {
            $search = mb_substr((string) $request->query('q', ''), 0, 100);
            $products = $shop->products()->when($search, fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('barcode', $search)))->orderBy('name')->paginate(30)->withQueryString();
            $members = $shop->members()->with('user')->get();

            return view('admin.shop-moderation', compact('shop', 'products', 'members', 'search'));
        }
        $canManage = backpack_user()->manages($shop, true) && $shop->status !== 'frozen';
        $stats = [
            'sales' => $shop->orders()->where('status', 'paid')->count(),
            'products' => $shop->products()->count(),
            'out_of_stock' => $shop->products()->where('quantity', 0)->count(),
            'users' => $shop->members()->count() + 1,
            'payments_to_review' => $shop->orders()->where('status', 'paid_review')->count(),
        ];
        $today = today();
        $salesByCurrency = $shop->orders()->where('status', 'paid')
            ->where('paid_at', '>=', $today)->where('paid_at', '<', $today->copy()->addDay())->select('currency')
            ->selectRaw('SUM(total) as revenue')->groupBy('currency')->orderBy('currency')->toBase()->get();

        return view('admin.workspace', compact('shop', 'canManage', 'stats', 'salesByCurrency'));
    }

    public function payments(string $shop): View
    {
        $shop = $this->shop($shop, true, true);
        $canManage = true;

        return view('admin.shop-payments', compact('shop', 'canManage'));
    }

    public function users(string $shop): View
    {
        $shop = $this->shop($shop, true, true);
        $shop->load('owner');
        $members = $shop->members()->with('user')->get();
        $canManage = true;

        return view('admin.shop-users', compact('shop', 'members', 'canManage'));
    }

    public function till(Request $request, string $shop, SalesService $sales): View
    {
        abort_if(backpack_user()->is_platform_admin, 403);
        $shop = $this->shop($shop);
        $canManage = backpack_user()->manages($shop, true) && $shop->status !== 'frozen';
        $canSell = $shop->status === 'approved';
        $search = mb_substr((string) $request->query('q', ''), 0, 100);
        $products = $shop->products()
            ->when($search, fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('barcode', $search)))
            ->orderBy('name')->paginate(30)->withQueryString();
        $discounts = $sales->activeDiscounts($shop);
        $productPrices = $products->getCollection()->mapWithKeys(fn ($product) => [$product->id => $sales->priceFor($product, $discounts)]);

        return view('admin.shop-till', compact('shop', 'canManage', 'canSell', 'search', 'products', 'productPrices'));
    }

    public function bulkProducts(Request $request): RedirectResponse
    {
        $data = $request->validate(['shop_id' => ['required', 'uuid']]);

        return $this->bulk($request, $data['shop_id']);
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
        $copy = $product->replicate(['barcode', 'sku', 'image']);
        $copy->name = Str::limit($product->name, 140, '').' (copy)';
        $copy->quantity = $product->quantity === null ? null : 0;
        $copy->status = 'approved';
        $sourcePath = Str::start($product->image ?? '', 'products/');
        if ($product->image && Storage::disk('public')->exists($sourcePath)) {
            $path = 'products/'.Str::uuid().'.'.pathinfo($sourcePath, PATHINFO_EXTENSION);
            Storage::disk('public')->copy($sourcePath, $path);
            $copy->image = $path;
        }
        $copy->save();

        return redirect()->route('product.edit', $copy->id)->with('success', 'Product duplicated. Set its barcode and stock before selling.');
    }

    public function bulk(Request $request, string $shop): RedirectResponse
    {
        $shop = $this->shop($shop, true);
        $data = $request->validate([
            'csv' => ['nullable', 'required_without:csv_file', 'string', 'max:200000'],
            'csv_file' => ['nullable', 'required_without:csv', 'file', 'mimes:csv,txt', 'max:200'],
        ]);
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Could not open CSV input.');
        }
        $csv = $data['csv'] ?? $request->file('csv_file')?->getContent() ?? '';
        fwrite($stream, $csv);
        rewind($stream);
        $header = fgetcsv($stream, escape: '');
        $currentHeader = ['name', 'description', 'cost_price', 'selling_price', 'sale_price', 'quantity', 'barcode', 'sku'];
        $legacyHeader = ['name', 'description', 'cost_price', 'selling_price', 'quantity', 'barcode', 'sku'];
        if ($header !== $currentHeader && $header !== $legacyHeader) {
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
                throw ValidationException::withMessages(['csv' => 'Each row needs the same columns as the header; import at most 100 products at a time.']);
            }
            $productRow = array_combine($header, array_map(fn ($value) => $value === '' ? null : $value, $row));
            $productRow['sale_price'] ??= null;
            $rows[] = $productRow;
        }
        fclose($stream);
        if (! $rows) {
            throw ValidationException::withMessages(['csv' => 'Add at least one product row.']);
        }
        DB::transaction(function () use ($shop, $rows) {
            foreach ($rows as $index => $row) {
                $row['visibility'] = 'published';
                $validator = Validator::make($row, ProductRequest::productRules($shop->id));
                if ($validator->fails()) {
                    throw ValidationException::withMessages(['csv' => 'Row '.($index + 2).': '.$validator->errors()->first()]);
                }
                $shop->products()->create($validator->validated());
            }
        });

        return back()->with('success', count($rows).' products created.');
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
