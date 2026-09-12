@php($price = $productPrices[$product->id])
<article class="product-card" data-product-card data-product-category="{{ $product->category_id }}" data-product-brand="{{ $product->brand_id }}" data-product-search="{{ Str::lower($product->name.' '.$product->barcode.' '.$product->description.' '.$product->category?->name.' '.$product->brand?->name) }}">
    <div class="product-image">@if($product->image)<img src="{{ Storage::disk('public')->url(Str::start($product->image, 'products/')) }}" alt="{{ $product->name }}" loading="lazy">@else<span aria-hidden="true">✦</span>@endif</div>
    <div class="product-body">
        <div class="product-meta">{{ $product->category?->name }}@if($product->category && $product->brand) · @endif{{ $product->brand?->name }}</div>
        <h3>{{ $product->name }}</h3>
        @if($isPreview && $product->visibility === 'draft')<span class="product-status">Draft</span>@endif
        <p class="preserve-lines">{{ $product->description }}</p>
        <div class="product-price"><strong>{{ $shop->currency }} {{ number_format($price['final'] / 100, 2) }}</strong>@if($price['final'] < (int) round((float) $product->selling_price * 100))<span><s>{{ $shop->currency }} {{ number_format((float) $product->selling_price, 2) }}</s>@if($price['discount_name']) &middot; {{ $price['discount_name'] }}@endif</span>@endif<span>{{ $product->quantity === null ? 'Available' : ($product->quantity > 0 ? $product->quantity.' in stock' : 'Sold out') }}</span></div>
        @if(! $isPreview && $product->quantity !== 0)
            <form class="add-cart-form" method="post" action="{{ route('cart.add', $shop->slug) }}" data-cart-form>
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <label class="quantity-label">Quantity<input type="number" name="quantity" value="1" min="1" max="{{ min($product->quantity ?? 10000, 10000) }}" aria-label="Quantity for {{ $product->name }}"></label>
                <button class="button small add-cart-button" type="submit" data-default-label="Add to cart">Add to cart</button>
            </form>
        @endif
    </div>
</article>
