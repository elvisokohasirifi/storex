@extends(backpack_view('blank'))
@section('header')
<section class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1>Inventory management</h1>
            <p class="text-muted">Manage stock receiving, returns, adjustments, low-stock alerts, and valuation across your shops.</p>
        </div>
        <a class="btn btn-outline-primary" href="{{ route('shop.index') }}">Manage shops</a>
    </div>
</section>
@endsection
@section('content')
@include('admin.messages')
<div class="row g-4 mb-4">
    @foreach([
        'shops' => ['Shops you administer', '#1d4ed8'],
        'managed_shops' => ['Inventory-enabled shops', '#047857'],
        'low_stock_products' => ['Low-stock products', '#be123c'],
        'cost_value' => ['Cost value', '#0f766e'],
        'potential_sales' => ['Potential sales', '#2563eb'],
        'potential_margin' => ['Potential margin', '#7c3aed'],
    ] as $key => [$label, $color])
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="card h-100 text-white" style="background-color: {{ $color }}">
                <div class="card-body">
                    <p class="mb-2">{{ $label }}</p>
                    <div class="h2 text-white mb-0">
                        @if(in_array($key, ['cost_value', 'potential_sales', 'potential_margin'], true))
                            {{ number_format($totals[$key], 2) }}
                        @else
                            {{ number_format($totals[$key]) }}
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card mb-4">
    <div class="card-header"><h3 class="card-title mb-0">Shop inventory workspaces</h3></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Shop</th><th>Status</th><th>Inventory management</th><th>Products</th><th>Low stock</th><th>Cost value</th><th>Potential sales</th><th></th></tr></thead>
                <tbody>
                    @forelse($shops as $shop)
                        @php
                            $costValue = $shop->products->sum(fn ($product) => (float) $product->cost_price * (int) $product->quantity);
                            $potentialSales = $shop->products->sum(fn ($product) => (float) $product->selling_price * (int) $product->quantity);
                        @endphp
                        <tr>
                            <td><strong>{{ $shop->name }}</strong><div class="text-muted small">{{ $shop->location }}</div></td>
                            <td><span class="badge bg-secondary text-white">{{ ucfirst($shop->status) }}</span></td>
                            <td>{{ $shop->enable_inventory_management ? 'Enabled' : 'Not enabled' }}</td>
                            <td>{{ number_format($shop->products_count) }}</td>
                            <td>@if($shop->low_stock_products_count > 0)<span class="badge bg-warning text-dark">{{ number_format($shop->low_stock_products_count) }} need attention</span>@else<span class="text-muted">None</span>@endif</td>
                            <td>{{ $shop->currency }} {{ number_format($costValue, 2) }}</td>
                            <td>{{ $shop->currency }} {{ number_format($potentialSales, 2) }}</td>
                            <td class="text-end">
                                @if($shop->enable_inventory_management && $shop->status !== 'frozen')
                                    <a class="btn btn-primary" href="{{ route('workspace.inventory', $shop) }}">Open inventory</a>
                                @elseif(! $shop->enable_inventory_management && $shop->status !== 'frozen')
                                    <a class="btn btn-outline-primary" href="{{ route('shop.edit', $shop) }}">Enable inventory</a>
                                @else
                                    <span class="text-muted">Frozen</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">You do not administer any shops yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
