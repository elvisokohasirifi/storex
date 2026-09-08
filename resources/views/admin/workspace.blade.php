@extends('admin.shop-page')
@section('page_title', 'Shop overview')
@section('shop_content')
<div class="row g-4 mb-4">
    @foreach([
        'sales' => ['Completed sales', '#1d4ed8'],
        'products' => ['Products set up', '#047857'],
        'out_of_stock' => ['Out of stock', '#be123c'],
        'users' => ['Shop users', '#7e22ce'],
        'payments_to_review' => ['Payments needing review', '#0e7490'],
    ] as $key => [$label, $color])
        <div class="col-12 col-sm-6 col-xl-4"><div class="card h-100 text-white" style="background-color: {{ $color }}"><div class="card-body"><p class="mb-2">{{ $label }}</p><div class="h1 text-white mb-0">{{ number_format($stats[$key]) }}</div></div></div></div>
    @endforeach
</div>
<div class="card mb-4"><div class="card-body"><h3>Sales today</h3><p class="text-muted">Sales completed today (UTC). Pending, cancelled, and payments needing review are excluded.</p>
    @forelse($salesByCurrency as $sales)<p class="h2">{{ $sales->currency }} {{ number_format($sales->revenue / 100, 2) }}</p>@empty<p class="text-muted mb-0">No completed sales today.</p>@endforelse
</div></div>
@endsection
