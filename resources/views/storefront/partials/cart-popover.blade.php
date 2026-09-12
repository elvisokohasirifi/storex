<h3>Your cart</h3>
<div data-cart-popover-items>
    @forelse($cartProducts as $cartProduct)
        <div class="cart-popover-item"><span>{{ $cartProduct->name }} × {{ $cart[$cartProduct->id] }}</span><strong>{{ $shop->currency }} {{ number_format(($productPrices[$cartProduct->id]['final'] * $cart[$cartProduct->id]) / 100, 2) }}</strong></div>
    @empty
        <p>Your cart is waiting for something good.</p>
    @endforelse
</div>
@if($cartProducts->isNotEmpty())
    <div class="cart-popover-total"><span>Total</span><strong data-cart-total>{{ $shop->currency }} {{ number_format($totals['total'] / 100, 2) }}</strong></div>
    <a class="button small" href="{{ route('cart.show', $shop->slug) }}">View cart ↗</a>
@endif
