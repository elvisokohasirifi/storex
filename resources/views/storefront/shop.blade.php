@extends('storefront.layout')
@section('title', $shop->name.' — Storex')
@section('shop-header')
<header class="shop-nav">
    <a class="shop-wordmark" href="{{ route('shops.show', $shop->slug) }}">
        @if($shop->logo)<img src="{{ Storage::disk('public')->url(Str::start($shop->logo, 'shops/')) }}" alt="{{ $shop->name }} logo">@else<span class="shop-monogram" aria-hidden="true">{{ Str::upper(Str::substr($shop->name, 0, 1)) }}</span>@endif
        <span>{{ $shop->name }}</span>
    </a>
    <nav aria-label="Shop navigation"><a href="#products">Products</a><a href="#about">About</a><a href="#contact">Contact</a></nav>
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
<section class="shop-hero">
    @if($shop->banner)<img class="shop-hero-banner" src="{{ Storage::disk('public')->url(Str::start($shop->banner, 'shops/')) }}" alt="{{ $shop->name }} banner">@else<div class="shop-hero-pattern" aria-hidden="true"><span>✦</span></div>@endif
    <div class="shop-hero-copy"><p class="eyebrow">LOCAL FINDS. EVERYDAY FAVOURITES.</p><h1>{{ $shop->name }}</h1><p class="shop-location">{{ $shop->location }}</p><div class="shop-hero-actions"><a class="button" href="#products">Explore products ↗</a><a href="#contact">Get in touch →</a></div></div>
</section>
<section class="shop-about" id="about">
    <div><p class="eyebrow">MEET YOUR SHOP</p><h2>A little about us.</h2></div>
    <div><p class="preserve-lines">{{ $shop->description ?: 'Welcome to '.$shop->name.'. Explore our products and get in touch — we would love to hear from you.' }}</p><a class="shop-text-link" href="#contact">Find us in {{ $shop->location }} ↗</a></div>
</section>
<section class="shop-section shop-products" id="products"><div class="section-heading"><div><p class="eyebrow">CURATED BY YOUR LOCAL SHOP</p><h2>Find something good.</h2></div><form class="search" method="get"><input name="q" value="{{ $search }}" placeholder="Search products or barcode" aria-label="Search products"><button aria-label="Search">↗</button></form></div>
@unless($isPreview)<form method="post" action="{{ route('checkout.store', $shop->slug) }}">@csrf @endunless
<div class="product-grid">@forelse($products as $product)@php($price = $productPrices[$product->id])<article class="product-card"><div class="product-image">@if($product->image)<img src="{{ Storage::disk('public')->url(Str::start($product->image, 'products/')) }}" alt="{{ $product->name }}" loading="lazy">@else<span aria-hidden="true">✦</span>@endif</div><div class="product-body"><div class="product-meta">{{ $product->category?->name }}@if($product->category && $product->brand) · @endif{{ $product->brand?->name }}</div><h3>{{ $product->name }}</h3>@if($isPreview && $product->visibility === 'draft')<span class="product-status">Draft</span>@endif<p class="preserve-lines">{{ $product->description }}</p><div class="product-price"><strong>{{ $shop->currency }} {{ number_format($price['final'] / 100, 2) }}</strong>@if($price['final'] < (int) round((float) $product->selling_price * 100))<span><s>{{ $shop->currency }} {{ number_format((float) $product->selling_price, 2) }}</s>@if($price['discount_name']) &middot; {{ $price['discount_name'] }}@endif</span>@endif<span>{{ $product->quantity === null ? 'Available' : ($product->quantity > 0 ? $product->quantity.' in stock' : 'Sold out') }}</span></div>
@if(! $isPreview && $product->quantity !== 0)<label class="quantity-label">Quantity<input type="number" name="items[{{ $product->id }}]" value="{{ old('items.'.$product->id, 0) }}" min="0" max="{{ min($product->quantity ?? 10000, 10000) }}" aria-label="Quantity for {{ $product->name }}"></label>@endif</div></article>@empty<div class="empty-state"><h3>No products to show yet.</h3><p>Check back soon for more good things.</p></div>@endforelse</div>
{{ $products->links() }}
@if(! $isPreview && $products->isNotEmpty())<section class="checkout-box"><div><p class="eyebrow">READY WHEN YOU ARE</p><h2>Make it yours.</h2><p>Choose quantities above, then enter your details. Your total is confirmed on Paystack before you pay.</p><p class="small-text">Stock is reserved for 15 minutes during checkout. Contact the shop to arrange collection or delivery. Submit the products on this page before browsing another page.</p></div><div class="checkout-fields">
<label>Full name<input name="customer_name" required maxlength="150" autocomplete="name" value="{{ old('customer_name') }}"></label><label>Email address (required by Paystack)<input type="email" name="customer_email" required autocomplete="email" value="{{ old('customer_email') }}"></label><label>Phone number<input type="tel" name="customer_phone" required maxlength="50" autocomplete="tel" value="{{ old('customer_phone') }}"></label><label>Delivery address or collection note (optional)<textarea name="delivery_address" rows="3">{{ old('delivery_address') }}</textarea></label>
@if($shop->paystack_secret_key)<button class="button" type="submit">Continue to secure payment ↗</button>@else<p class="notice">Online payments are not enabled yet. Contact the shop to purchase.</p>@endif</div></section>@endif @unless($isPreview)</form>@endunless</section>
<section class="shop-contact" id="contact">
    <div><p class="eyebrow">LET’S TALK</p><h2>Good things start<br>with a hello.</h2><p>Questions about a product, collection, or delivery? Contact the shop directly.</p></div>
    <dl>
        <div><dt>Visit us</dt><dd><a href="https://www.google.com/maps/search/?api=1&amp;query={{ rawurlencode($shop->location) }}" target="_blank" rel="noopener noreferrer">{{ $shop->location }} ↗</a></dd></div>
        @if($shop->email)<div><dt>Email us</dt><dd><a href="mailto:{{ $shop->email }}">{{ $shop->email }} ↗</a></dd></div>@endif
        @if($contacts->isNotEmpty())<div><dt>Contact details</dt><dd>@foreach($contacts as $contact)<div>@if($contact['phone'])<a href="tel:{{ $contact['phone'] }}">{{ $contact['label'] }} ↗</a>@else{{ $contact['label'] }}@endif</div>@endforeach</dd></div>@endif
    </dl>
</section>
@endsection
