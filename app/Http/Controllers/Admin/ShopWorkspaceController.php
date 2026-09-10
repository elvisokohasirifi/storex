<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\StockBatch;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function inventoryManagement(): View
    {
        abort_if(backpack_user()->is_platform_admin, 403);

        $user = backpack_user();
        $shops = $user->accessibleShops()
            ->where(function ($query) use ($user): void {
                $query->where('owner_id', $user->id)
                    ->orWhereHas('members', fn ($query) => $query->where('user_id', $user->id)->where('role', 'admin'));
            })
            ->withCount([
                'products',
                'products as low_stock_products_count' => fn ($query) => $query->whereNotNull('quantity')->whereColumn('quantity', '<=', 'reorder_level'),
            ])
            ->with(['products:id,shop_id,name,quantity,reorder_level,cost_price,selling_price'])
            ->orderBy('name')
            ->get();

        $totals = [
            'shops' => $shops->count(),
            'managed_shops' => $shops->where('enable_inventory_management', true)->count(),
            'low_stock_products' => $shops->sum('low_stock_products_count'),
            'cost_value' => $shops->flatMap->products->sum(fn (Product $product): float => (float) $product->cost_price * (int) $product->quantity),
            'potential_sales' => $shops->flatMap->products->sum(fn (Product $product): float => (float) $product->selling_price * (int) $product->quantity),
        ];
        $totals['potential_margin'] = $totals['potential_sales'] - $totals['cost_value'];

        return view('admin.inventory-management', compact('shops', 'totals'));
    }

    public function operations(string $shop): View
    {
        abort_if(backpack_user()->is_platform_admin, 403);
        $shop = $this->shop($shop, true, true);
        $products = $shop->products()->orderBy('name')->get();
        $suppliers = $shop->suppliers()->orderBy('name')->get();
        $purchaseOrders = $shop->purchaseOrders()->with('supplier', 'items.product')->latest()->limit(20)->get();
        $pendingManualOrders = $shop->orders()->with('items')->where('channel', 'manual')->where('status', 'pending')->latest()->limit(20)->get();
        $customers = $shop->customers()->withCount('orders')->orderBy('name')->limit(50)->get();
        $shifts = $shop->tillShifts()->with('user')->latest('opened_at')->limit(20)->get();
        $openShift = $shop->tillShifts()->where('user_id', backpack_user()->id)->where('status', 'open')->latest('opened_at')->first();
        $transferShops = backpack_user()->accessibleShops()->where('id', '!=', $shop->id)->where('status', '!=', 'frozen')->orderBy('name')->get();
        $expiringBatches = $shop->stockBatches()->with('product')->whereNotNull('expiry_date')->where('quantity_remaining', '>', 0)->where('expiry_date', '<=', now()->addDays(30)->toDateString())->orderBy('expiry_date')->limit(20)->get();
        $today = today();
        $ordersToday = $shop->orders()->where('status', 'paid')->where('paid_at', '>=', $today)->where('paid_at', '<', $today->copy()->addDay())->with('items')->get();
        $summary = [
            'sales_count' => $ordersToday->count(),
            'revenue' => $ordersToday->sum('total'),
            'gross_profit' => $ordersToday->sum(fn (Order $order): int => $order->items->sum(fn ($item): int => $item->unit_cost === null ? 0 : (($item->unit_price - $item->unit_cost) * $item->quantity))),
            'discounts' => $ordersToday->sum('discount_total'),
            'cash_expected' => $ordersToday->where('payment_method', 'cash')->sum('total'),
        ];
        $canManage = true;

        return view('admin.shop-operations', compact('shop', 'products', 'suppliers', 'purchaseOrders', 'pendingManualOrders', 'customers', 'shifts', 'openShift', 'transferShops', 'expiringBatches', 'summary', 'canManage'));
    }

    public function variant(Request $request, string $shop): RedirectResponse
    {
        $shop = $this->shop($shop, true, true);
        $data = $request->validate([
            'product_id' => ['required', 'uuid', Rule::exists('products', 'id')->where('shop_id', $shop->id)],
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:100'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'selling_price' => ['nullable', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'quantity' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'reorder_level' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ]);
        $product = $shop->products()->whereKey($data['product_id'])->firstOrFail();
        $variant = $product->variants()->create($data + ['reorder_level' => 10]);
        AuditLog::record($shop, 'product_variant.created', $variant, ['name' => $variant->name]);

        return back()->with('success', 'Product variant added.');
    }

    public function transferStock(Request $request, string $shop): RedirectResponse
    {
        $shop = $this->inventoryWriteShop($shop);
        $data = $request->validate([
            'target_shop_id' => ['required', 'uuid', Rule::exists('shops', 'id')],
            'product_id' => ['required', 'uuid', Rule::exists('products', 'id')->where('shop_id', $shop->id)],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
            'reason' => ['nullable', 'string', 'max:10000'],
        ]);
        $targetShop = backpack_user()->accessibleShops()->whereKey($data['target_shop_id'])->firstOrFail();
        abort_unless(backpack_user()->manages($targetShop, true) && $targetShop->enable_inventory_management && $targetShop->status !== 'frozen', 403);
        DB::transaction(function () use ($shop, $targetShop, $data): void {
            $sourceProduct = $shop->products()->whereKey($data['product_id'])->lockForUpdate()->firstOrFail();
            if ((int) $sourceProduct->quantity < $data['quantity']) {
                throw ValidationException::withMessages(['quantity' => 'Not enough stock to transfer.']);
            }
            $targetProduct = $targetShop->products()->where('sku', $sourceProduct->sku)->when(! $sourceProduct->sku, fn ($query) => $query->where('name', $sourceProduct->name))->lockForUpdate()->first();
            if (! $targetProduct) {
                $targetProduct = $sourceProduct->replicate(['barcode', 'image']);
                $targetProduct->shop_id = $targetShop->id;
                $targetProduct->quantity = 0;
                $targetProduct->save();
            }
            $sourceProduct->decrement('quantity', $data['quantity']);
            $targetProduct->increment('quantity', $data['quantity']);
            foreach ([[$shop, $sourceProduct, 'transfer_out', -$data['quantity']], [$targetShop, $targetProduct, 'transfer_in', $data['quantity']]] as [$movementShop, $product, $type, $quantity]) {
                $product->movements()->create(['shop_id' => $movementShop->id, 'type' => $type, 'quantity' => $quantity, 'reason' => $data['reason'] ?? 'Stock transfer', 'user_id' => backpack_user()->id]);
            }
            AuditLog::record($shop, 'stock.transferred', $sourceProduct, ['target_shop_id' => $targetShop->id, 'quantity' => $data['quantity']]);
        }, 3);

        return back()->with('success', 'Stock transferred.');
    }

    public function supplier(Request $request, string $shop): RedirectResponse
    {
        $shop = $this->shop($shop, true, true);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
        $supplier = $shop->suppliers()->updateOrCreate(['name' => $data['name']], $data);
        AuditLog::record($shop, 'supplier.saved', $supplier, ['name' => $supplier->name]);

        return back()->with('success', 'Supplier saved.');
    }

    public function purchaseOrder(Request $request, string $shop): RedirectResponse
    {
        $shop = $this->shop($shop, true, true);
        $data = $request->validate([
            'supplier_id' => ['nullable', 'uuid', Rule::exists('suppliers', 'id')->where('shop_id', $shop->id)],
            'product_id' => ['required', 'uuid', Rule::exists('products', 'id')->where('shop_id', $shop->id)],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
        $unitCost = ($data['unit_cost'] ?? null) === null ? null : (string) $data['unit_cost'];
        $purchaseOrder = $shop->purchaseOrders()->create([
            'supplier_id' => $data['supplier_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'total_cost' => $unitCost === null ? 0 : SalesService::minorUnits($unitCost) * $data['quantity'],
        ]);
        $purchaseOrder->items()->create(['product_id' => $data['product_id'], 'quantity' => $data['quantity'], 'unit_cost' => $unitCost]);
        AuditLog::record($shop, 'purchase_order.created', $purchaseOrder, ['quantity' => $data['quantity']]);

        return back()->with('success', 'Purchase order created.');
    }

    public function receivePurchaseOrder(string $shop, string $purchaseOrder): RedirectResponse
    {
        $shop = $this->inventoryWriteShop($shop);
        DB::transaction(function () use ($shop, $purchaseOrder): void {
            $purchaseOrder = $shop->purchaseOrders()->whereKey($purchaseOrder)->lockForUpdate()->firstOrFail();
            $purchaseOrder->load('items');
            abort_if($purchaseOrder->status === 'received', 422);
            foreach ($purchaseOrder->items as $item) {
                $product = $shop->products()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();
                $product->increment('quantity', $item->quantity);
                if ($item->unit_cost !== null) {
                    $product->forceFill(['cost_price' => $item->unit_cost])->save();
                }
                $movement = $product->movements()->create([
                    'shop_id' => $shop->id,
                    'type' => 'purchase',
                    'quantity' => $item->quantity,
                    'reference_type' => PurchaseOrder::class,
                    'reference_id' => $purchaseOrder->id,
                    'reason' => 'Purchase order '.$purchaseOrder->reference,
                    'user_id' => backpack_user()->id,
                ]);
                StockBatch::create([
                    'shop_id' => $shop->id,
                    'product_id' => $product->id,
                    'supplier_id' => $purchaseOrder->supplier_id,
                    'inventory_movement_id' => $movement->id,
                    'quantity_received' => $item->quantity,
                    'quantity_remaining' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                ]);
            }
            $purchaseOrder->update(['status' => 'received', 'received_at' => now()]);
            AuditLog::record($shop, 'purchase_order.received', $purchaseOrder);
        }, 3);

        return back()->with('success', 'Purchase order received into stock.');
    }

    public function confirmManualSale(string $shop, Order $order, SalesService $sales): RedirectResponse
    {
        $shop = $this->shop($shop, true);
        abort_unless($order->shop_id === $shop->id, 404);
        $order = $sales->confirmManual($order, backpack_user());

        if ($order->status === 'paid_review') {
            return back()->with('warning', 'Payment recorded, but stock could not be fulfilled. Review this sale before handing over items.');
        }

        return back()->with('success', 'Manual payment confirmed and stock updated.');
    }

    public function openShift(Request $request, string $shop): RedirectResponse
    {
        abort_if(backpack_user()->is_platform_admin, 403);
        $shop = $this->shop($shop, true);
        $data = $request->validate(['opening_cash' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2']]);
        if ($shop->tillShifts()->where('user_id', backpack_user()->id)->where('status', 'open')->exists()) {
            throw ValidationException::withMessages(['shift' => 'You already have an open till shift.']);
        }
        $shift = $shop->tillShifts()->create(['user_id' => backpack_user()->id, 'opening_cash' => SalesService::minorUnits((string) ($data['opening_cash'] ?? '0')), 'opened_at' => now()]);
        AuditLog::record($shop, 'shift.opened', $shift);

        return back()->with('success', 'Till shift opened.');
    }

    public function closeShift(Request $request, string $shop, string $shift): RedirectResponse
    {
        abort_if(backpack_user()->is_platform_admin, 403);
        $shop = $this->shop($shop, true);
        $shift = $shop->tillShifts()->whereKey($shift)->firstOrFail();
        abort_unless($shift->user_id === backpack_user()->id || backpack_user()->manages($shop, true), 403);
        if ($shift->status !== 'open') {
            throw ValidationException::withMessages(['shift' => 'This till shift is already closed.']);
        }
        $data = $request->validate(['actual_cash' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'], 'notes' => ['nullable', 'string', 'max:10000']]);
        $cashSales = $shift->orders()->where('status', 'paid')->where('payment_method', 'cash')->sum('total');
        $shift->update(['status' => 'closed', 'expected_cash' => $shift->opening_cash + $cashSales, 'actual_cash' => SalesService::minorUnits((string) $data['actual_cash']), 'notes' => $data['notes'] ?? null, 'closed_at' => now()]);
        AuditLog::record($shop, 'shift.closed', $shift, ['expected_cash' => $shift->expected_cash, 'actual_cash' => $shift->actual_cash]);

        return back()->with('success', 'Till shift closed.');
    }

    public function cancelSale(Request $request, string $shop, Order $order, SalesService $sales): RedirectResponse
    {
        $shop = $this->shop($shop, true, true);
        abort_unless($order->shop_id === $shop->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:10000']]);
        $sales->cancel($order, $data['reason']);

        return back()->with('success', 'Sale cancelled and stock reversed where needed.');
    }

    public function refundSale(Request $request, string $shop, Order $order, SalesService $sales): RedirectResponse
    {
        $shop = $this->shop($shop, true, true);
        abort_unless($order->shop_id === $shop->id, 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:10000']]);
        $sales->refund($order, $data['reason']);

        return back()->with('success', 'Sale refunded and stock reversed where needed.');
    }

    public function exportProducts(string $shop): StreamedResponse
    {
        $shop = $this->shop($shop, true, true);

        return response()->streamDownload(function () use ($shop): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name', 'sku', 'barcode', 'quantity', 'reorder_level', 'cost_price', 'selling_price']);
            $shop->products()->orderBy('name')->each(fn (Product $product) => fputcsv($out, [$product->name, $product->sku, $product->barcode, $product->quantity, $product->reorder_level, $product->cost_price, $product->selling_price]));
            fclose($out);
        }, Str::slug($shop->name).'-products.csv');
    }

    public function bulkPrices(Request $request, string $shop): RedirectResponse
    {
        $shop = $this->shop($shop, true, true);
        $data = $request->validate(['csv' => ['required', 'string', 'max:200000']]);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $data['csv']);
        rewind($stream);
        $header = fgetcsv($stream, escape: '');
        if ($header !== ['sku', 'barcode', 'selling_price', 'sale_price']) {
            throw ValidationException::withMessages(['csv' => 'Use the header sku,barcode,selling_price,sale_price.']);
        }
        DB::transaction(function () use ($shop, $stream): void {
            while (($row = fgetcsv($stream, escape: '')) !== false) {
                [$sku, $barcode, $sellingPrice, $salePrice] = array_pad($row, 4, null);
                $product = $shop->products()->where(function ($query) use ($sku, $barcode): void {
                    $query->when($sku, fn ($query) => $query->orWhere('sku', $sku))->when($barcode, fn ($query) => $query->orWhere('barcode', $barcode));
                })->first();
                if (! $product) {
                    continue;
                }
                $updates = [];
                if ($sellingPrice !== null && $sellingPrice !== '') {
                    $updates['selling_price'] = $sellingPrice;
                }
                if ($salePrice !== null) {
                    $updates['sale_price'] = $salePrice === '' ? null : $salePrice;
                }
                if ($updates !== []) {
                    $product->update($updates);
                    AuditLog::record($shop, 'product.price_updated', $product, $updates);
                }
            }
        });
        fclose($stream);

        return back()->with('success', 'Bulk prices updated.');
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
        $openShift = $shop->tillShifts()->where('user_id', backpack_user()->id)->where('status', 'open')->latest('opened_at')->first();
        $discounts = $sales->activeDiscounts($shop);
        $productPrices = $products->getCollection()->mapWithKeys(fn ($product) => [$product->id => $sales->priceFor($product, $discounts)]);

        return view('admin.shop-till', compact('shop', 'canManage', 'canSell', 'search', 'products', 'productPrices', 'openShift'));
    }

    public function inventory(string $shop): View
    {
        abort_if(backpack_user()->is_platform_admin, 403);
        $shop = $this->shop($shop, true, true);
        abort_unless($shop->enable_inventory_management, 404);

        $products = $shop->products()->withCount('movements')->orderBy('name')->paginate(30);
        $allProducts = $shop->products()->orderBy('name')->get();
        $suppliers = $shop->suppliers()->orderBy('name')->get();
        $lowStockProducts = $allProducts->whereNotNull('quantity')
            ->filter(fn (Product $product): bool => $product->quantity <= $product->reorder_level)
            ->values();
        $valuation = $shop->products()
            ->selectRaw('COALESCE(SUM(COALESCE(quantity, 0) * COALESCE(cost_price, 0)), 0) as cost_value')
            ->selectRaw('COALESCE(SUM(COALESCE(quantity, 0) * selling_price), 0) as potential_sales')
            ->toBase()->first();
        $valuation = [
            'cost_value' => (float) $valuation->cost_value,
            'potential_sales' => (float) $valuation->potential_sales,
            'potential_margin' => (float) $valuation->potential_sales - (float) $valuation->cost_value,
        ];
        $canManage = true;

        return view('admin.shop-inventory', compact('shop', 'products', 'allProducts', 'suppliers', 'lowStockProducts', 'valuation', 'canManage'));
    }

    public function stock(string $shop, string $product): View
    {
        abort_if(backpack_user()->is_platform_admin, 403);
        $shop = $this->shop($shop, true, true);
        abort_unless($shop->enable_inventory_management, 404);
        $product = $shop->products()->with('variants')->whereKey($product)->firstOrFail();
        $movements = $product->movements()->with('user')->latest('created_at')->paginate(50);
        $canManage = true;

        return view('admin.product-stock', compact('shop', 'product', 'movements', 'canManage'));
    }

    public function receiveStock(Request $request, string $shop): RedirectResponse
    {
        $shop = $this->inventoryWriteShop($shop);
        $data = $request->validate([
            'product_id' => ['required', 'uuid', Rule::exists('products', 'id')->where('shop_id', $shop->id)],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
            'supplier_id' => ['nullable', 'uuid', Rule::exists('suppliers', 'id')->where('shop_id', $shop->id)],
            'batch_number' => ['nullable', 'string', 'max:100'],
            'expiry_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:10000'],
        ]);

        DB::transaction(function () use ($shop, $data): void {
            $product = $shop->products()->whereKey($data['product_id'])->lockForUpdate()->firstOrFail();
            $product->increment('quantity', $data['quantity']);
            if (array_key_exists('unit_cost', $data) && $data['unit_cost'] !== null) {
                $product->forceFill(['cost_price' => $data['unit_cost']])->save();
            }
            $movement = $product->movements()->create([
                'shop_id' => $shop->id,
                'type' => 'purchase',
                'quantity' => $data['quantity'],
                'reason' => $data['reason'] ?? 'Stock received',
                'user_id' => backpack_user()->id,
            ]);
            if (! empty($data['batch_number']) || ! empty($data['expiry_date']) || ! empty($data['supplier_id'])) {
                StockBatch::create([
                    'shop_id' => $shop->id,
                    'product_id' => $product->id,
                    'supplier_id' => $data['supplier_id'] ?? null,
                    'inventory_movement_id' => $movement->id,
                    'batch_number' => $data['batch_number'] ?? null,
                    'expiry_date' => $data['expiry_date'] ?? null,
                    'quantity_received' => $data['quantity'],
                    'quantity_remaining' => $data['quantity'],
                    'unit_cost' => $data['unit_cost'] ?? null,
                ]);
            }
            AuditLog::record($shop, 'stock.received', $product, ['quantity' => $data['quantity']]);
        }, 3);

        return back()->with('success', 'Stock received.');
    }

    public function adjustStock(Request $request, string $shop): RedirectResponse
    {
        $shop = $this->inventoryWriteShop($shop);
        $data = $request->validate([
            'product_id' => ['required', 'uuid', Rule::exists('products', 'id')->where('shop_id', $shop->id)],
            'actual_quantity' => ['required', 'integer', 'min:0', 'max:100000000'],
            'reason' => ['required', Rule::in(['adjustment', 'damage', 'expired'])],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        DB::transaction(function () use ($shop, $data): void {
            $product = $shop->products()->whereKey($data['product_id'])->lockForUpdate()->firstOrFail();
            $difference = $data['actual_quantity'] - (int) $product->quantity;
            if ($difference === 0) {
                throw ValidationException::withMessages(['actual_quantity' => 'The actual quantity already matches the system quantity.']);
            }
            $movementType = $difference < 0 && in_array($data['reason'], ['damage', 'expired'], true) ? $data['reason'] : 'adjustment';
            $product->forceFill(['quantity' => $data['actual_quantity']])->save();
            $product->movements()->create([
                'shop_id' => $shop->id,
                'type' => $movementType,
                'quantity' => $difference,
                'reason' => trim(ucfirst(str_replace('_', ' ', $data['reason'])).($data['notes'] ? ': '.$data['notes'] : '')),
                'user_id' => backpack_user()->id,
            ]);
        }, 3);

        return back()->with('success', 'Stock adjusted.');
    }

    public function returnStock(Request $request, string $shop): RedirectResponse
    {
        $shop = $this->inventoryWriteShop($shop);
        $data = $request->validate([
            'product_id' => ['required', 'uuid', Rule::exists('products', 'id')->where('shop_id', $shop->id)],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
            'reason' => ['nullable', 'string', 'max:10000'],
        ]);

        DB::transaction(function () use ($shop, $data): void {
            $product = $shop->products()->whereKey($data['product_id'])->lockForUpdate()->firstOrFail();
            $product->increment('quantity', $data['quantity']);
            $product->movements()->create([
                'shop_id' => $shop->id,
                'type' => 'return',
                'quantity' => $data['quantity'],
                'reason' => $data['reason'] ?? 'Returned stock',
                'user_id' => backpack_user()->id,
            ]);
        }, 3);

        return back()->with('success', 'Returned stock added back to inventory.');
    }

    private function inventoryWriteShop(string $shop): Shop
    {
        abort_if(backpack_user()->is_platform_admin, 403);
        $shop = $this->shop($shop, true, true);
        abort_unless($shop->enable_inventory_management, 404);

        return $shop;
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
            'momo_number' => ['nullable', 'string', 'max:50', 'regex:/^\+?[0-9][0-9\s().-]{5,48}[0-9]$/'],
            'momo_account_name' => ['nullable', 'string', 'max:255'],
        ]);
        if ($shop->orders()->where('status', 'pending')->where('expires_at', '>', now())->exists() && $shop->currency !== $data['currency']) {
            throw ValidationException::withMessages(['currency' => 'Wait for pending checkouts to expire before changing currency.']);
        }
        $shop->currency = $data['currency'];
        $shop->momo_number = $data['momo_number'] ?? null;
        $shop->momo_account_name = $data['momo_account_name'] ?? null;
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
        $currentHeader = ['name', 'description', 'cost_price', 'selling_price', 'sale_price', 'quantity', 'reorder_level', 'barcode', 'sku'];
        $previousHeader = ['name', 'description', 'cost_price', 'selling_price', 'sale_price', 'quantity', 'barcode', 'sku'];
        $legacyHeader = ['name', 'description', 'cost_price', 'selling_price', 'quantity', 'barcode', 'sku'];
        if ($header !== $currentHeader && $header !== $previousHeader && $header !== $legacyHeader) {
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
            $productRow['reorder_level'] ??= 10;
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
