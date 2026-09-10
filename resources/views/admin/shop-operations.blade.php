@extends('admin.shop-page')
@section('page_title', 'Shop operations')
@section('shop_content')
<div class="row g-4 mb-4">
    @foreach([
        'sales_count' => ['Sales today', '#1d4ed8', false],
        'revenue' => ['Revenue today', '#047857', true],
        'gross_profit' => ['Gross profit today', '#7c3aed', true],
        'discounts' => ['Discounts today', '#be123c', true],
        'cash_expected' => ['Expected cash today', '#0f766e', true],
    ] as $key => [$label, $color, $money])
        <div class="col-12 col-sm-6 col-xl-4"><div class="card h-100 text-white" style="background-color: {{ $color }}"><div class="card-body"><p class="mb-2">{{ $label }}</p><div class="h2 text-white mb-0">{{ $money ? $shop->currency.' '.number_format($summary[$key] / 100, 2) : number_format($summary[$key]) }}</div></div></div></div>
    @endforeach
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3 class="card-title mb-0">Till shift</h3></div><div class="card-body">
        @if($openShift)
            <p class="text-success fw-semibold">Open since {{ $openShift->opened_at->format('M j, g:i A') }}</p>
            <form method="post" action="{{ route('workspace.shift.close', [$shop, $openShift]) }}">@csrf
                <label class="form-label w-100">Actual cash at close<input class="form-control" name="actual_cash" type="number" min="0" step="0.01" required></label>
                <label class="form-label w-100">Notes<textarea class="form-control" name="notes" rows="2"></textarea></label>
                <button class="btn btn-primary">Close till shift</button>
            </form>
        @else
            <form method="post" action="{{ route('workspace.shift.open', $shop) }}">@csrf
                <label class="form-label w-100">Opening cash<input class="form-control" name="opening_cash" type="number" min="0" step="0.01" value="0.00"></label>
                <button class="btn btn-success">Open till shift</button>
            </form>
        @endif
    </div></div></div>

    <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3 class="card-title mb-0">Supplier</h3></div><div class="card-body">
        <form method="post" action="{{ route('workspace.supplier', $shop) }}">@csrf
            <label class="form-label w-100">Name<input class="form-control" name="name" required maxlength="150"></label>
            <div class="row g-3"><div class="col-md-6"><label class="form-label w-100">Phone<input class="form-control" name="phone" maxlength="50"></label></div><div class="col-md-6"><label class="form-label w-100">Email<input class="form-control" type="email" name="email"></label></div></div>
            <label class="form-label w-100">Notes<textarea class="form-control" name="notes" rows="2"></textarea></label>
            <button class="btn btn-primary">Save supplier</button>
        </form>
    </div></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3 class="card-title mb-0">Create purchase order</h3></div><div class="card-body">
        <form method="post" action="{{ route('workspace.purchase-order', $shop) }}">@csrf
            <label class="form-label w-100">Supplier<select class="form-select" name="supplier_id"><option value="">No supplier selected</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->name }}</option>@endforeach</select></label>
            <label class="form-label w-100">Product<select class="form-select" name="product_id" required>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }} — {{ $product->quantity }} in stock</option>@endforeach</select></label>
            <div class="row g-3"><div class="col-md-6"><label class="form-label w-100">Quantity<input class="form-control" type="number" min="1" step="1" name="quantity" required></label></div><div class="col-md-6"><label class="form-label w-100">Unit cost<input class="form-control" type="number" min="0" step="0.01" name="unit_cost"></label></div></div>
            <label class="form-label w-100">Notes<textarea class="form-control" name="notes" rows="2"></textarea></label>
            <button class="btn btn-primary">Create purchase order</button>
        </form>
    </div></div></div>

    <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3 class="card-title mb-0">Bulk price update</h3></div><div class="card-body">
        <p class="small text-muted">Use header <code>sku,barcode,selling_price,sale_price</code>. SKU or barcode can identify the product.</p>
        <form method="post" action="{{ route('workspace.bulk-prices', $shop) }}">@csrf
            <textarea class="form-control font-monospace" name="csv" rows="7" placeholder="sku,barcode,selling_price,sale_price&#10;BREAD-01,,12.00,10.00" required></textarea>
            <div class="d-flex flex-wrap gap-2 mt-3"><button class="btn btn-primary">Update prices</button><a class="btn btn-outline-primary" href="{{ route('workspace.exports.products', $shop) }}">Export products CSV</a></div>
        </form>
    </div></div></div>
</div>


<div class="row g-4 mb-4">
    <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3 class="card-title mb-0">Product variant</h3></div><div class="card-body">
        <form method="post" action="{{ route('workspace.variant', $shop) }}">@csrf
            <label class="form-label w-100">Base product<select class="form-select" name="product_id" required>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select></label>
            <label class="form-label w-100">Variant name<input class="form-control" name="name" placeholder="Small, Medium, 1kg, 5kg" required maxlength="150"></label>
            <div class="row g-3"><div class="col-md-6"><label class="form-label w-100">SKU<input class="form-control" name="sku" maxlength="100"></label></div><div class="col-md-6"><label class="form-label w-100">Barcode<input class="form-control" name="barcode" maxlength="100"></label></div></div>
            <div class="row g-3"><div class="col-md-6"><label class="form-label w-100">Selling price<input class="form-control" type="number" min="0.01" step="0.01" name="selling_price"></label></div><div class="col-md-6"><label class="form-label w-100">Cost price<input class="form-control" type="number" min="0" step="0.01" name="cost_price"></label></div></div>
            <div class="row g-3"><div class="col-md-6"><label class="form-label w-100">Quantity<input class="form-control" type="number" min="0" step="1" name="quantity"></label></div><div class="col-md-6"><label class="form-label w-100">Reorder level<input class="form-control" type="number" min="0" step="1" name="reorder_level" value="10"></label></div></div>
            <button class="btn btn-primary">Add variant</button>
        </form>
    </div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3 class="card-title mb-0">Transfer stock</h3></div><div class="card-body">
        @if($transferShops->isNotEmpty() && $shop->enable_inventory_management)
            <form method="post" action="{{ route('workspace.transfer', $shop) }}">@csrf
                <label class="form-label w-100">Product<select class="form-select" name="product_id" required>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }} — {{ $product->quantity }} in stock</option>@endforeach</select></label>
                <label class="form-label w-100">Target shop<select class="form-select" name="target_shop_id" required>@foreach($transferShops as $transferShop)<option value="{{ $transferShop->id }}">{{ $transferShop->name }}</option>@endforeach</select></label>
                <label class="form-label w-100">Quantity<input class="form-control" type="number" min="1" step="1" name="quantity" required></label>
                <label class="form-label w-100">Reason<textarea class="form-control" name="reason" rows="2"></textarea></label>
                <button class="btn btn-primary">Transfer stock</button>
            </form>
        @else
            <p class="text-muted mb-0">Enable inventory management on this shop and another administered shop to transfer stock.</p>
        @endif
    </div></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3 class="card-title mb-0">Purchase orders</h3></div><div class="card-body table-responsive">
        <table class="table table-hover align-middle"><thead><tr><th>Reference</th><th>Supplier</th><th>Status</th><th>Total</th><th></th></tr></thead><tbody>@forelse($purchaseOrders as $purchaseOrder)<tr><td>{{ $purchaseOrder->reference }}</td><td>{{ $purchaseOrder->supplier?->name ?: '—' }}</td><td>{{ ucfirst($purchaseOrder->status) }}</td><td>{{ $shop->currency }} {{ number_format($purchaseOrder->total_cost / 100, 2) }}</td><td>@if($shop->enable_inventory_management && $purchaseOrder->status !== 'received')<form method="post" action="{{ route('workspace.purchase-order.receive', [$shop, $purchaseOrder]) }}">@csrf<button class="btn btn-sm btn-success">Receive</button></form>@endif</td></tr>@empty<tr><td colspan="5">No purchase orders yet.</td></tr>@endforelse</tbody></table>
    </div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3 class="card-title mb-0">Customers</h3></div><div class="card-body table-responsive">
        <table class="table table-hover"><thead><tr><th>Name</th><th>Phone</th><th>Email</th><th>Orders</th></tr></thead><tbody>@forelse($customers as $customer)<tr><td>{{ $customer->name }}</td><td>{{ $customer->phone }}</td><td>{{ $customer->email ?: '—' }}</td><td>{{ $customer->orders_count }}</td></tr>@empty<tr><td colspan="4">Customers will appear here after sales are recorded.</td></tr>@endforelse</tbody></table>
    </div></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3 class="card-title mb-0">Expiring batches</h3></div><div class="card-body">
        @forelse($expiringBatches as $batch)<div class="d-flex justify-content-between border-bottom py-2"><div><strong>{{ $batch->product->name }}</strong><div class="small text-muted">Batch {{ $batch->batch_number ?: '—' }} · {{ $batch->quantity_remaining }} remaining</div></div><span class="badge bg-warning text-dark">{{ $batch->expiry_date->format('M j, Y') }}</span></div>@empty<p class="text-muted mb-0">No batches expire in the next 30 days.</p>@endforelse
    </div></div></div>
    <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3 class="card-title mb-0">Recent shifts</h3></div><div class="card-body table-responsive">
        <table class="table"><thead><tr><th>User</th><th>Status</th><th>Expected</th><th>Actual</th></tr></thead><tbody>@forelse($shifts as $shift)<tr><td>{{ $shift->user->name }}</td><td>{{ ucfirst($shift->status) }}</td><td>{{ $shop->currency }} {{ number_format($shift->expected_cash / 100, 2) }}</td><td>{{ $shift->actual_cash === null ? '—' : $shop->currency.' '.number_format($shift->actual_cash / 100, 2) }}</td></tr>@empty<tr><td colspan="4">No till shifts yet.</td></tr>@endforelse</tbody></table>
    </div></div></div>
</div>
@endsection
