@extends(backpack_view('blank'))
@section('header')
<section class="container-fluid">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div><h1>{{ $shop->name }}</h1><p class="text-muted">@yield('page_title') · {{ ucfirst($shop->status) }}</p></div>
        <div class="d-flex flex-wrap gap-2">
            @if($canManage)
                <a class="btn btn-primary" href="{{ route('shop.edit', $shop) }}">Edit shop</a>
            @endif
            @if($shop->status === 'approved' || backpack_user()->id === $shop->owner_id)
                <a class="btn btn-outline-primary" href="{{ route('shops.show', $shop->slug) }}">Open storefront ↗</a>
            @endif
        </div>
    </div>
</section>
@endsection
@section('content')
@include('admin.messages')
<nav class="d-flex flex-wrap gap-2 mb-4" aria-label="Shop management">
    <a class="btn {{ request()->routeIs('workspace.show') ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('workspace.show', $shop) }}">Overview</a>
    <a class="btn btn-outline-primary" href="{{ route('product.index', ['shop_id' => $shop->id]) }}">Products</a>
    @if($canManage && $shop->enable_inventory_management)
        <a class="btn {{ request()->routeIs('workspace.inventory', 'workspace.stock') ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('workspace.inventory', $shop) }}">Inventory management</a>
    @endif
    @if($canManage)
        <a class="btn {{ request()->routeIs('workspace.operations') ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('workspace.operations', $shop) }}">Operations</a>
        <a class="btn {{ request()->routeIs('shop-discount.*') ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('shop-discount.index', ['shop_id' => $shop->id]) }}">Discounts</a>
    @endif
    <a class="btn {{ request()->routeIs('workspace.till') ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('workspace.till', $shop) }}">Till / point of sale</a>
    @if($canManage)
        <a class="btn {{ request()->routeIs('workspace.users') ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('workspace.users', $shop) }}">Users</a>
        <a class="btn {{ request()->routeIs('workspace.payments') ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('workspace.payments', $shop) }}">Payment credentials</a>
    @endif
</nav>
@yield('shop_content')
@endsection
