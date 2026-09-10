@extends('storefront.layout')
@section('title', 'Cart — '.$shop->name)
@section('shop-header')
<header class="shop-nav">
    <a class="shop-wordmark" href="{{ route('shops.show', $shop->slug) }}">@if($shop->logo)<img src="{{ Storage::disk('public')->url(Str::start($shop->logo, 'shops/')) }}" alt="{{ $shop->name }} logo">@else<span class="shop-monogram" aria-hidden="true">{{ Str::upper(Str::substr($shop->name, 0, 1)) }}</span>@endif</a>
    <nav aria-label="Shop navigation"><a href="{{ route('shops.show', $shop->slug) }}#products">Products</a><a href="{{ route('shops.show', $shop->slug) }}#contact">Contact</a></nav>
    <a class="cart-trigger" href="{{ route('cart.show', $shop->slug) }}" aria-label="View cart"><span aria-hidden="true">&#128722;</span><strong>Cart</strong><em>{{ array_sum($cart) }}</em></a>
    <a class="shop-platform-link" href="{{ route('home') }}">on storex. ↗</a>
</header>
@endsection
@section('content')
<section class="cart-page">
    @if(session('success'))<p class="notice" role="status">{{ session('success') }}</p>@endif
    <div class="cart-heading"><p class="eyebrow">SHOPPING CART</p><h1>Your cart</h1><a class="shop-text-link" href="{{ route('shops.show', $shop->slug) }}#products">Keep shopping ↗</a></div>
    @if($cartProducts->isEmpty())
        <div class="empty-state"><h3>Your cart is empty.</h3><p>Add products from {{ $shop->name }} to start checkout.</p><a class="button" href="{{ route('shops.show', $shop->slug) }}#products">Browse products ↗</a></div>
    @else
        <form method="post" action="{{ route('cart.update', $shop->slug) }}">
            @csrf
            @method('PUT')
            <div class="cart-layout">
                <div class="cart-card">
                    <table class="cart-table">
                        <thead><tr><th>Product</th><th>Price</th><th>Quantity</th><th>Total</th></tr></thead>
                        <tbody>
                            @foreach($cartProducts as $product)
                                @php($price = $productPrices[$product->id])
                                <tr>
                                    <td><strong>{{ $product->name }}</strong><span>{{ $product->category?->name }}{{ $product->category && $product->brand ? ' · ' : '' }}{{ $product->brand?->name }}</span></td>
                                    <td>{{ $shop->currency }} {{ number_format($price['final'] / 100, 2) }}</td>
                                    <td><input type="number" name="items[{{ $product->id }}]" value="{{ old('items.'.$product->id, $cart[$product->id]) }}" min="0" max="{{ min($product->quantity ?? 10000, 10000) }}" aria-label="Quantity for {{ $product->name }}"><small>Set to 0 to remove</small></td>
                                    <td><strong>{{ $shop->currency }} {{ number_format(($price['final'] * $cart[$product->id]) / 100, 2) }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <button class="button secondary" type="submit">Update cart</button>
                </div>
                <aside class="summary-card">
                    <h2>Order summary</h2>
                    <p><span>Subtotal</span><strong>{{ $shop->currency }} {{ number_format($totals['subtotal'] / 100, 2) }}</strong></p>
                    @if($totals['discount'] > 0)<p><span>Discount</span><strong>-{{ $shop->currency }} {{ number_format($totals['discount'] / 100, 2) }}</strong></p>@endif
                    <p class="summary-total"><span>Total</span><strong>{{ $shop->currency }} {{ number_format($totals['total'] / 100, 2) }}</strong></p>
                    <a class="button" href="{{ route('checkout.show', $shop->slug) }}">Proceed to checkout ↗</a>
                </aside>
            </div>
        </form>
    @endif
</section>
@endsection
