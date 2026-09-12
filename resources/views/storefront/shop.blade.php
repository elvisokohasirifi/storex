@extends('storefront.layout')
@section('title', $shop->name.' — Storex')
@section('shop-header')
<header class="shop-nav">
    <a class="shop-wordmark" href="{{ route('shops.show', $shop->slug) }}">
        @if($shop->logo)<img src="{{ Storage::disk('public')->url(Str::start($shop->logo, 'shops/')) }}" alt="{{ $shop->name }} logo">@else<span class="shop-monogram" aria-hidden="true">{{ Str::upper(Str::substr($shop->name, 0, 1)) }}</span>@endif
    </a>
    <nav aria-label="Shop navigation"><a href="#products">Products</a><a href="#about">About</a><a href="#contact">Contact</a></nav>
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
<section class="storefront-moderation" aria-label="Shop moderation">
    @if(session('success'))<p class="notice" role="status">{{ session('success') }}</p>@endif
    <form method="post" action="{{ route('moderation.store') }}">
        @csrf
        <input type="hidden" name="type" value="shops">
        <input type="hidden" name="ids[]" value="{{ $shop->id }}">
        <label for="shop-moderation-reason">Reason for freezing or rejecting this shop</label>
        <textarea id="shop-moderation-reason" name="reason" rows="2" required maxlength="2000" placeholder="Explain the issue that needs to be addressed.">{{ old('reason') }}</textarea>
        <div class="moderation-actions">
            <button class="button freeze-shop" name="status" value="frozen">Freeze shop</button>
            <button class="button reject-shop" name="status" value="rejected">Reject shop</button>
        </div>
    </form>
</section>
@endif
@if($isOwnerPreview)
<div class="shop-preview" role="status"><span><strong>Owner preview</strong> · Shop {{ ucfirst($shop->status) }} · Products visible · Checkout disabled in preview</span><a href="{{ route('workspace.show', $shop) }}">Back to shop overview ↗</a></div>
@endif
@if(session('success'))<div class="notice" role="status" data-cart-message>{{ session('success') }}</div>@else<div class="notice cart-message" role="status" data-cart-message hidden></div>@endif
<section class="shop-hero">
    @if($shop->banner)<img class="shop-hero-banner" src="{{ Storage::disk('public')->url(Str::start($shop->banner, 'shops/')) }}" alt="{{ $shop->name }} banner">@else<div class="shop-hero-pattern" aria-hidden="true"><span>✦</span></div>@endif
    <div class="shop-hero-copy"><p class="eyebrow">LOCAL FINDS. EVERYDAY FAVOURITES.</p><h1>{{ $shop->name }}</h1><p class="shop-location">{{ $shop->location }}</p><div class="shop-hero-actions"><a class="button" href="{{ route('shops.products', $shop->slug) }}">Explore all products ↗</a><a href="#contact">Get in touch →</a></div></div>
</section>
<section class="shop-about" id="about">
    <div><p class="eyebrow">MEET YOUR SHOP</p><h2>A little about us.</h2></div>
    <div><p class="preserve-lines">{{ $shop->description ?: 'Welcome to '.$shop->name.'. Explore our products and get in touch — we would love to hear from you.' }}</p><a class="shop-text-link" href="#contact">Find us in {{ $shop->location }} ↗</a></div>
</section>
<section class="shop-section shop-products" id="products">
    <div class="section-heading"><div><p class="eyebrow">FEATURED PRODUCTS</p><h2>Start with these finds.</h2></div><a class="button secondary" href="{{ route('shops.products', $shop->slug) }}">View all products ↗</a></div>
    <div class="product-grid">
        @forelse($products as $product)
            @include('storefront.partials.product-card', ['product' => $product, 'productPrices' => $productPrices])
        @empty
            <div class="empty-state"><h3>No products to show yet.</h3><p>Check back soon for more good things.</p></div>
        @endforelse
    </div>
</section>
<section class="shop-contact" id="contact">
    <div><p class="eyebrow">LET’S TALK</p><h2>Good things start<br>with a hello.</h2><p>Questions about a product, collection, or delivery? Contact the shop directly.</p></div>
    <dl>
        <div><dt>Visit us</dt><dd><a href="https://www.google.com/maps/search/?api=1&amp;query={{ rawurlencode($shop->location) }}" target="_blank" rel="noopener noreferrer">{{ $shop->location }} ↗</a></dd></div>
        @if($shop->email)<div><dt>Email us</dt><dd><a href="mailto:{{ $shop->email }}">{{ $shop->email }} ↗</a></dd></div>@endif
        @if($contacts->isNotEmpty())<div><dt>Contact details</dt><dd>@foreach($contacts as $contact)<div>@if($contact['phone'])<a href="tel:{{ $contact['phone'] }}">{{ $contact['label'] }} ↗</a>@else{{ $contact['label'] }}@endif</div>@endforeach</dd></div>@endif
    </dl>
</section>
@endsection

@push('scripts')
<script src="{{ asset('js/storefront-cart.js') }}?v={{ filemtime(public_path('js/storefront-cart.js')) }}" defer></script>
@endpush
