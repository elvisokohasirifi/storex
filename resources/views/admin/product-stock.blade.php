@extends('admin.shop-page')
@section('page_title', 'Stock details')
@section('shop_content')
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
            <div>
                <h2 class="mb-1">{{ $product->name }}</h2>
                <p class="text-muted mb-0">SKU: {{ $product->sku ?: '—' }}</p>
                <p class="text-muted mb-0">Barcode: {{ $product->barcode ?: '—' }}</p>
            </div>
            <a class="btn btn-success" href="{{ route('workspace.inventory', $shop) }}#receive-stock">Receive Stock</a>
        </div>
        <hr>
        <div class="row g-4">
            <div class="col-6 col-lg-3"><div class="border rounded p-3 h-100"><div class="text-muted">Current Stock</div><div class="h2 mb-0">{{ $product->quantity }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="border rounded p-3 h-100"><div class="text-muted">Reorder Level</div><div class="h2 mb-0">{{ $product->reorder_level }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="border rounded p-3 h-100"><div class="text-muted">Average Cost</div><div class="h2 mb-0">{{ $shop->currency }} {{ number_format((float) $product->cost_price, 2) }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="border rounded p-3 h-100"><div class="text-muted">Stock Value</div><div class="h2 mb-0">{{ $shop->currency }} {{ number_format((float) $product->cost_price * (int) $product->quantity, 2) }}</div></div></div>
        </div>
    </div>
</div>


@if($product->variants->isNotEmpty())
<div class="card mb-4">
    <div class="card-header"><h3 class="card-title mb-0">Variants</h3></div>
    <div class="card-body table-responsive">
        <table class="table table-hover"><thead><tr><th>Name</th><th>SKU</th><th>Barcode</th><th>Price</th><th>Stock</th><th>Reorder level</th></tr></thead><tbody>
            @foreach($product->variants as $variant)<tr><td>{{ $variant->name }}</td><td>{{ $variant->sku ?: '—' }}</td><td>{{ $variant->barcode ?: '—' }}</td><td>{{ $variant->selling_price === null ? 'Uses product price' : $shop->currency.' '.number_format((float) $variant->selling_price, 2) }}</td><td>{{ $variant->quantity ?? 'Uses product stock' }}</td><td>{{ $variant->reorder_level }}</td></tr>@endforeach
        </tbody></table>
    </div>
</div>
@endif

<div class="card mb-4">
    <div class="card-header"><h3 class="card-title mb-0">Recent Movements</h3></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>Quantity</th><th>Type</th><th>Reason</th><th>User</th><th>Date</th></tr></thead>
                <tbody>
                    @forelse($movements as $movement)
                        <tr>
                            <td class="fw-semibold {{ $movement->quantity > 0 ? 'text-success' : 'text-danger' }}">{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $movement->type)) }}</td>
                            <td>{{ $movement->reason ?: '—' }}</td>
                            <td>{{ $movement->user?->name ?: 'System' }}</td>
                            <td>{{ $movement->created_at?->format('M j, g:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No stock movements recorded yet.</td></tr>
                    @endforelse
                    <tr class="table-light"><td class="fw-bold">{{ $product->quantity }}</td><td colspan="4" class="fw-bold">Current stock</td></tr>
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $movements->links() }}</div>
    </div>
</div>
@endsection
