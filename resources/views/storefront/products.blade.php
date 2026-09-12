@extends('storefront.layout')
@section('title', 'Products — '.$shop->name.' — Storex')
@section('shop-header')
<header class="shop-nav">
    <a class="shop-wordmark" href="{{ route('shops.show', $shop->slug) }}">
        @if($shop->logo)<img src="{{ Storage::disk('public')->url(Str::start($shop->logo, 'shops/')) }}" alt="{{ $shop->name }} logo">@else<span class="shop-monogram" aria-hidden="true">{{ Str::upper(Str::substr($shop->name, 0, 1)) }}</span>@endif
    </a>
    <nav aria-label="Shop navigation"><a href="{{ route('shops.show', $shop->slug) }}">Home</a><a href="{{ route('shops.show', $shop->slug) }}#contact">Contact</a></nav>
    @unless($isPreview)
        <div class="shop-cart" data-cart-root>
            <a class="cart-trigger" href="{{ route('cart.show', $shop->slug) }}" aria-label="View cart">
                <span aria-hidden="true">&#128722;</span><strong>Cart</strong><em data-cart-count>{{ array_sum($cart) }}</em>
            </a>
            <div class="cart-popover" role="status" data-cart-popover>
                @include('storefront.partials.cart-popover', ['productPrices' => $cartProductPrices, 'totals' => $cartTotals])
            </div>
        </div>
    @endunless
    <a class="shop-platform-link" href="{{ route('home') }}">on storex. ↗</a>
</header>
@endsection
@section('content')
@if($isAdminPreview)
<div class="shop-preview" role="status"><span><strong>Admin preview</strong> · Shop {{ ucfirst($shop->status) }} · Products visible</span><a href="{{ route('workspace.show', $shop) }}">Back to moderation ↗</a></div>
@endif
@if($isOwnerPreview)
<div class="shop-preview" role="status"><span><strong>Owner preview</strong> · Shop {{ ucfirst($shop->status) }} · Products visible · Checkout disabled in preview</span><a href="{{ route('workspace.show', $shop) }}">Back to shop overview ↗</a></div>
@endif
@if(session('success'))<div class="notice" role="status" data-cart-message>{{ session('success') }}</div>@else<div class="notice cart-message" role="status" data-cart-message hidden></div>@endif
<section class="shop-section shop-products all-products-page" id="products" data-product-browser>
    <div class="section-heading">
        <div><p class="eyebrow">ALL PRODUCTS</p><h1>{{ $shop->name }} products</h1><p class="product-count"><span data-visible-product-count>{{ $products->count() }}</span> of {{ $products->count() }} products shown</p></div>
        <a class="shop-text-link" href="{{ route('shops.show', $shop->slug) }}">Back to shop ↗</a>
    </div>
    <div class="product-search-panel">
        <label for="product-search">Search products</label>
        <input id="product-search" type="search" placeholder="Search by name, barcode, category, or brand" data-product-search-input autocomplete="off">
    </div>
    <div @class(['product-showcase', 'has-filters' => $categories->isNotEmpty() || $brands->isNotEmpty()])>
        @if($categories->isNotEmpty() || $brands->isNotEmpty())
            <aside class="product-filters" aria-label="Product filters">
                <div class="filter-heading"><p class="eyebrow">FILTER PRODUCTS</p><button type="button" data-clear-product-filters>Clear all</button></div>
                @if($categories->isNotEmpty())
                    <div class="filter-group" data-filter-group="category">
                        <h3>Categories</h3>
                        <button class="filter-option active" type="button" data-filter-type="category" data-filter-value="" aria-pressed="true"><span>All categories</span><em>{{ $products->count() }}</em></button>
                        @foreach($categories as $category)
                            <button class="filter-option" type="button" data-filter-type="category" data-filter-value="{{ $category->id }}" aria-pressed="false"><span>{{ $category->name }}</span><em>{{ $category->products_count }}</em></button>
                        @endforeach
                    </div>
                @endif
                @if($brands->isNotEmpty())
                    <div class="filter-group" data-filter-group="brand">
                        <h3>Brands</h3>
                        <button class="filter-option active" type="button" data-filter-type="brand" data-filter-value="" aria-pressed="true"><span>All brands</span><em>{{ $products->count() }}</em></button>
                        @foreach($brands as $brand)
                            <button class="filter-option" type="button" data-filter-type="brand" data-filter-value="{{ $brand->id }}" aria-pressed="false"><span>{{ $brand->name }}</span><em>{{ $brand->products_count }}</em></button>
                        @endforeach
                    </div>
                @endif
            </aside>
        @endif
        <div class="product-results">
            <div class="product-grid">
                @forelse($products as $product)
                    @include('storefront.partials.product-card', ['product' => $product, 'productPrices' => $productPrices])
                @empty
                    <div class="empty-state"><h3>No products to show yet.</h3><p>Check back soon for more good things.</p></div>
                @endforelse
            </div>
            <div class="empty-state product-filter-empty" data-product-filter-empty hidden><h3>No products match your filters.</h3><p>Try another search term, category, or brand.</p></div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('js/storefront-cart.js') }}" defer></script>
<script src="{{ asset('js/storefront-products.js') }}" defer></script>
@endpush
