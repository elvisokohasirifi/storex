@extends('admin.shop-page')
@section('page_title', 'Inventory management')
@section('shop_content')
<div class="row g-4 mb-4">
    @foreach([
        'cost_value' => ['Cost value', '#0f766e'],
        'potential_sales' => ['Potential sales', '#2563eb'],
        'potential_margin' => ['Potential margin', '#7c3aed'],
    ] as $key => [$label, $color])
        <div class="col-12 col-md-4">
            <div class="card h-100 text-white" style="background-color: {{ $color }}">
                <div class="card-body">
                    <p class="mb-2">{{ $label }}</p>
                    <div class="h2 text-white mb-0">{{ $shop->currency }} {{ number_format($valuation[$key], 2) }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-4 mb-4 align-items-stretch">
    <div class="col-lg-5">
        <div class="card h-100 border-success" id="receive-stock">
            <div class="card-header bg-success text-white"><h3 class="card-title mb-0 text-white">Receive Stock</h3></div>
            <div class="card-body">
                <form method="post" action="{{ route('workspace.inventory.receive', $shop) }}">
                    @csrf
                    <label class="form-label w-100">Product
                        <select class="form-select" name="product_id" required>
                            @foreach($allProducts as $stockProduct)<option value="{{ $stockProduct->id }}">{{ $stockProduct->name }} — {{ $stockProduct->quantity }} in stock</option>@endforeach
                        </select>
                    </label>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label w-100">Quantity received<input class="form-control" type="number" min="1" step="1" name="quantity" required></label></div>
                        <div class="col-md-6"><label class="form-label w-100">Unit cost<input class="form-control" type="number" min="0" step="0.01" name="unit_cost" placeholder="Optional"></label></div>
                    </div>
                    <label class="form-label w-100">Supplier<select class="form-select" name="supplier_id"><option value="">No supplier selected</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></label>
                    <div class="row g-3"><div class="col-md-6"><label class="form-label w-100">Batch number<input class="form-control" name="batch_number" maxlength="100"></label></div><div class="col-md-6"><label class="form-label w-100">Expiry date<input class="form-control" type="date" name="expiry_date"></label></div></div>
                    <label class="form-label w-100">Notes<textarea class="form-control" name="reason" rows="3" placeholder="Supplier, invoice number, or delivery note"></textarea></label>
                    <button class="btn btn-success w-100" type="submit">Receive Stock</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title mb-0">⚠ Low Stock</h3></div>
            <div class="card-body">
                @forelse($lowStockProducts as $lowStockProduct)
                    @php($suggestedQuantity = max(0, ($lowStockProduct->reorder_level * 3) - (int) $lowStockProduct->quantity))
                    <div class="d-flex justify-content-between gap-3 border-bottom py-2">
                        <div>
                            <strong>{{ $lowStockProduct->name }}</strong>
                            <div class="text-muted small">Current: {{ $lowStockProduct->quantity }} · Reorder level: {{ $lowStockProduct->reorder_level }}</div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-warning text-dark">{{ $lowStockProduct->quantity }} remaining</span>
                            <div class="small text-muted">Suggested quantity: {{ $suggestedQuantity }}</div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">No products are at or below reorder level.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title mb-0">Stock Adjustment</h3></div>
            <div class="card-body">
                <form method="post" action="{{ route('workspace.inventory.adjust', $shop) }}" data-stock-adjustment>
                    @csrf
                    <label class="form-label w-100">Product
                        <select class="form-select" name="product_id" required data-adjust-product>
                            @foreach($allProducts as $stockProduct)<option value="{{ $stockProduct->id }}" data-current="{{ $stockProduct->quantity }}">{{ $stockProduct->name }} — system quantity: {{ $stockProduct->quantity }}</option>@endforeach
                        </select>
                    </label>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label w-100">Actual quantity<input class="form-control" type="number" min="0" step="1" name="actual_quantity" required data-adjust-actual></label></div>
                        <div class="col-md-6"><label class="form-label w-100">Reason<select class="form-select" name="reason" required><option value="adjustment">Stock count correction</option><option value="damage">Damaged</option><option value="expired">Expired</option></select></label></div>
                    </div>
                    <p class="fw-semibold" data-adjust-difference>Difference: —</p>
                    <label class="form-label w-100">Notes<textarea class="form-control" name="notes" rows="3" placeholder="Example: 3 loaves damaged during delivery"></textarea></label>
                    <button class="btn btn-primary" type="submit">Adjust Stock</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title mb-0">Returns</h3></div>
            <div class="card-body">
                <form method="post" action="{{ route('workspace.inventory.return', $shop) }}">
                    @csrf
                    <label class="form-label w-100">Product
                        <select class="form-select" name="product_id" required>
                            @foreach($allProducts as $stockProduct)<option value="{{ $stockProduct->id }}">{{ $stockProduct->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="form-label w-100">Quantity returned<input class="form-control" type="number" min="1" step="1" name="quantity" required></label>
                    <label class="form-label w-100">Reason<textarea class="form-control" name="reason" rows="3" placeholder="Customer return, restocked after correction, or other note"></textarea></label>
                    <button class="btn btn-outline-primary" type="submit">Record Return</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h3 class="card-title mb-0">Products</h3></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Product</th><th>SKU</th><th>Barcode</th><th>Current stock</th><th>Reorder level</th><th>Cost</th><th>Selling</th><th>Stock value</th><th>Movements</th></tr></thead>
                <tbody>
                    @forelse($products as $product)
                        <tr @class(['table-warning' => $product->quantity <= $product->reorder_level])>
                            <td><a href="{{ route('workspace.stock', [$shop, $product]) }}">{{ $product->name }}</a></td>
                            <td>{{ $product->sku ?: '—' }}</td>
                            <td>{{ $product->barcode ?: '—' }}</td>
                            <td>{{ $product->quantity }}</td>
                            <td>{{ $product->reorder_level }}</td>
                            <td>{{ $shop->currency }} {{ number_format((float) $product->cost_price, 2) }}</td>
                            <td>{{ $shop->currency }} {{ number_format((float) $product->selling_price, 2) }}</td>
                            <td>{{ $shop->currency }} {{ number_format((float) $product->cost_price * (int) $product->quantity, 2) }}</td>
                            <td>{{ number_format($product->movements_count) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9">No products yet. Add products before managing stock.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $products->links() }}</div>
    </div>
</div>
@push('after_scripts')
<script>
(() => {
    const form = document.querySelector('[data-stock-adjustment]');
    if (!form) return;
    const select = form.querySelector('[data-adjust-product]');
    const actual = form.querySelector('[data-adjust-actual]');
    const difference = form.querySelector('[data-adjust-difference]');
    const update = () => {
        const current = parseInt(select.selectedOptions[0]?.dataset.current || '0', 10) || 0;
        const next = actual.value === '' ? null : parseInt(actual.value, 10);
        difference.textContent = next === null || Number.isNaN(next) ? 'Difference: —' : `Difference: ${next - current}`;
    };
    select.addEventListener('change', update);
    actual.addEventListener('input', update);
    update();
})();
</script>
@endpush
@endsection
