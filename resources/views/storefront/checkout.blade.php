@extends('storefront.layout')
@section('title', 'Checkout — '.$shop->name)
@section('shop-header')
<header class="shop-nav">
    <a class="shop-wordmark" href="{{ route('shops.show', $shop->slug) }}">@if($shop->logo)<img src="{{ Storage::disk('public')->url(Str::start($shop->logo, 'shops/')) }}" alt="{{ $shop->name }} logo">@else<span class="shop-monogram" aria-hidden="true">{{ Str::upper(Str::substr($shop->name, 0, 1)) }}</span>@endif</a>
    <nav aria-label="Shop navigation"><a href="{{ route('cart.show', $shop->slug) }}">Cart</a><a href="{{ route('shops.products', $shop->slug) }}">Products</a><a href="{{ route('shops.show', $shop->slug) }}#contact">Contact</a></nav>
    <a class="shop-platform-link" href="{{ route('home') }}">on storex. ↗</a>
</header>
@endsection
@section('content')
<section class="checkout-page">
    <div class="cart-heading"><p class="eyebrow">SECURE CHECKOUT</p><h1>Checkout</h1><a class="shop-text-link" href="{{ route('cart.show', $shop->slug) }}">Back to cart ↗</a></div>
    <form method="post" action="{{ route('checkout.store', $shop->slug) }}">
        @csrf
        @foreach($cartProducts as $product)
            <input type="hidden" name="items[{{ $product->id }}]" value="{{ $cart[$product->id] }}">
        @endforeach
        <div class="checkout-layout">
            <div class="checkout-panel">
                <h2>Customer details</h2>
                <div class="checkout-fields">
                    <label>Full name<input name="customer_name" required maxlength="150" autocomplete="name" value="{{ old('customer_name') }}"></label>
                    <label>Email address @if($shop->paystack_secret_key)<span>(required for Paystack)</span>@else<span>(optional)</span>@endif<input type="email" name="customer_email" @if($shop->paystack_secret_key) required @endif autocomplete="email" value="{{ old('customer_email') }}"></label>
                    <label>Phone number<input type="tel" name="customer_phone" required maxlength="50" autocomplete="tel" value="{{ old('customer_phone') }}"></label>
                    <label>Delivery address or collection note (optional)<textarea name="delivery_address" rows="3">{{ old('delivery_address') }}</textarea></label>
                </div>
                <h2>Payment method</h2>
                <div class="payment-methods">
                    @if($shop->paystack_secret_key)
                        <label class="payment-option"><input type="radio" name="payment_method" value="paystack" checked><span><strong>Pay online with Paystack</strong><small>Card, bank, USSD or wallet options available on Paystack.</small></span></label>
                    @else
                        <label class="payment-option"><input type="radio" name="payment_method" value="cash" @checked(old('payment_method', 'cash') === 'cash')><span><strong>Pay with cash</strong><small>Pay the shop when you collect or receive your items.</small></span></label>
                        @if($shop->momo_number)
                            <label class="payment-option"><input type="radio" name="payment_method" value="momo" @checked(old('payment_method') === 'momo')><span><strong>Pay with mobile money</strong><small>Send payment to {{ $shop->momo_account_name ?: $shop->name }} on {{ $shop->momo_number }} after placing the order.</small></span></label>
                        @else
                            <p class="notice">Mobile money is not enabled for this shop yet. Cash payment is available.</p>
                        @endif
                    @endif
                </div>
            </div>
            <aside class="summary-card">
                <h2>Order summary</h2>
                @foreach($cartProducts as $product)
                    @php($price = $productPrices[$product->id])
                    <p><span>{{ $product->name }} × {{ $cart[$product->id] }}</span><strong>{{ $shop->currency }} {{ number_format(($price['final'] * $cart[$product->id]) / 100, 2) }}</strong></p>
                @endforeach
                <p><span>Subtotal</span><strong>{{ $shop->currency }} {{ number_format($totals['subtotal'] / 100, 2) }}</strong></p>
                @if($totals['discount'] > 0)<p><span>Discount</span><strong>-{{ $shop->currency }} {{ number_format($totals['discount'] / 100, 2) }}</strong></p>@endif
                <p class="summary-total"><span>Total</span><strong>{{ $shop->currency }} {{ number_format($totals['total'] / 100, 2) }}</strong></p>
                <button class="button" type="submit">Place order ↗</button>
                <p class="small-text">Stock is reserved for 15 minutes after you place the order.</p>
            </aside>
        </div>
    </form>
</section>
@endsection
