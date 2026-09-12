@extends('admin.shop-page')
@section('page_title', 'Till / point of sale')
@section('shop_content')
@unless($canSell)<div class="alert alert-warning">Sales are available once this shop is approved.</div>@endunless
<div class="alert alert-info d-none" data-offline-warning>Connection looks offline. Keep this page open and record the sale once the device is back online.</div>
@if($openShift)<div class="alert alert-success">Till shift open since {{ $openShift->opened_at->format('M j, g:i A') }}. <a href="{{ route('workspace.operations', $shop) }}">Close shift</a></div>@else<div class="alert alert-warning">No till shift is open. <a href="{{ route('workspace.operations', $shop) }}">Open a shift</a> before cashing up.</div>@endif
<div class="card mb-4"><div class="card-header"><h3 class="card-title">Till / point of sale</h3></div><div class="card-body">
<form method="get" class="d-flex flex-column flex-md-row gap-2 mb-3" data-barcode-pos data-barcode-target="q" data-submit-on-scan>
    <input class="form-control" name="q" value="{{ $search }}" placeholder="Search product name or scan barcode" aria-label="Search products">
    <button class="btn btn-outline-primary" type="submit">Search</button>
    <button class="btn btn-outline-primary" type="button" data-barcode-scan><i class="la la-barcode" aria-hidden="true"></i> Scan barcode</button>
    <p class="small text-muted mb-0 align-self-md-center" data-barcode-status role="status" aria-live="polite"></p>
</form>
@if($canSell)<form method="post" action="{{ route('workspace.sale', $shop) }}" data-pos-form data-currency="{{ $shop->currency }}">@csrf @endif
<div class="row g-4 align-items-start">
    <div class="col-lg-8">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Barcode</th>
                        <th>Visibility</th>
                        <th>Price</th>
                        <th>Stock</th>
                        @if($canSell)<th>Cart</th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        @php($price = $productPrices[$product->id])
                        <tr @if($product->barcode) data-product-barcode="{{ $product->barcode }}" @endif data-pos-product data-product-id="{{ $product->id }}" data-product-name="{{ $product->name }}" data-product-price="{{ $price['final'] }}" data-product-stock="{{ $product->quantity ?? '' }}">
                            <td><a href="{{ route('product.show', $product) }}">{{ $product->name }}</a></td>
                            <td>{{ $product->barcode ?: '—' }}</td>
                            <td>{{ ucfirst($product->visibility) }}</td>
                            <td>{{ $shop->currency }} {{ number_format($price['final'] / 100, 2) }}@if($price['final'] < (int) round((float) $product->selling_price * 100)) <span class="text-muted"><s>{{ $shop->currency }} {{ number_format((float) $product->selling_price, 2) }}</s></span>@endif</td>
                            <td>{{ $product->quantity ?? 'Untracked' }}</td>
                            @if($canSell)
                                <td>
                                    <input type="hidden" name="items[{{ $product->id }}]" value="{{ old('items.'.$product->id, 0) }}" data-sale-quantity>
                                    <button class="btn btn-sm btn-primary" type="button" data-cart-add>Add to cart</button>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canSell ? 6 : 5 }}">No products match your search. Add products from the Products page.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $products->links() }}</div>
    </div>
    @if($canSell)
        <aside class="col-lg-4">
            <div class="card position-sticky" style="top: 1rem;" data-pos-cart>
                <div class="card-header"><h3 class="card-title mb-0">Cart</h3></div>
                <div class="card-body">
                    <div class="text-muted text-center py-4" data-cart-empty>No products added yet.</div>
                    <div class="vstack gap-3" data-cart-items></div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center h4"><span>Total</span><strong data-cart-total>{{ $shop->currency }} 0.00</strong></div>
                    <div class="row mt-3 g-2">
                        <div class="col-12"><label class="form-label">Customer name<input class="form-control" name="customer_name" required maxlength="150" value="{{ old('customer_name', 'Walk-in customer') }}"></label></div>
                        <div class="col-12"><label class="form-label">Customer email (optional)<input class="form-control" type="email" name="customer_email" value="{{ old('customer_email') }}"></label></div>
                        <div class="col-12"><label class="form-label">Phone number<input class="form-control" type="tel" name="customer_phone" required maxlength="50" value="{{ old('customer_phone') }}"></label></div>
                        <div class="col-12"><label class="form-label">Payment method<select class="form-select" name="payment_method"><option value="cash">Cash</option><option value="momo">Mobile money</option><option value="card">Card</option><option value="bank_transfer">Bank transfer</option><option value="other">Other</option></select></label></div>
                    </div>
                    <button class="btn btn-primary w-100 mt-3" type="submit" data-cart-submit disabled>Record cash sale & issue receipt</button>
                    <p class="text-muted small mt-2 mb-0">Record a cash sale only after receiving payment. Stock and discounts are checked again on submission.</p>
                </div>
            </div>
        </aside>
    @endif
</div>
@if($canSell)</form>@endif
</div></div>
@push('after_scripts')
<script src="{{ asset('barcode-scanner.js') }}"></script>
<script>
(() => {
    const form = document.querySelector('[data-pos-form]');
    if (!form) return;
    const currency = form.dataset.currency || '';
    const items = form.querySelector('[data-cart-items]');
    const empty = form.querySelector('[data-cart-empty]');
    const total = form.querySelector('[data-cart-total]');
    const submit = form.querySelector('[data-cart-submit]');
    const money = (amount) => `${currency} ${(amount / 100).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    const cssEscape = (value) => window.CSS?.escape ? CSS.escape(value) : value.replace(/["\\]/g, '\\$&');
    const rows = new Map();
    const productFor = (id) => form.querySelector(`[data-pos-product][data-product-id="${cssEscape(id)}"]`);
    const sourceInputFor = (id) => productFor(id)?.querySelector('[data-sale-quantity]');
    const render = () => {
        let cartTotal = 0;
        let count = 0;
        rows.forEach((row, id) => {
            const input = sourceInputFor(id);
            const quantity = parseInt(input?.value || '0', 10) || 0;
            if (quantity < 1) {
                row.remove();
                rows.delete(id);
                return;
            }
            const price = parseInt(row.dataset.price || '0', 10) || 0;
            row.querySelector('[data-cart-line-quantity]').value = quantity;
            row.querySelector('[data-cart-line-total]').textContent = money(price * quantity);
            cartTotal += price * quantity;
            count += quantity;
        });
        empty.classList.toggle('d-none', rows.size > 0);
        total.textContent = money(cartTotal);
        submit.disabled = count === 0;
    };
    const addRow = (product, quantity = 1) => {
        const id = product.dataset.productId;
        const source = sourceInputFor(id);
        const current = parseInt(source.value || '0', 10) || 0;
        const stock = product.dataset.productStock === '' ? null : parseInt(product.dataset.productStock, 10);
        source.value = String(stock === null ? current + quantity : Math.min(stock, current + quantity));
        if (!rows.has(id)) {
            const row = document.createElement('div');
            row.className = 'border rounded p-2';
            row.dataset.cartLine = id;
            row.dataset.price = product.dataset.productPrice;
            row.innerHTML = `
                <div class="d-flex justify-content-between gap-2">
                    <strong class="fs-5 fw-semibold lh-sm"></strong>
                    <button class="btn btn-sm btn-link text-danger p-0" type="button" data-cart-remove>Remove</button>
                </div>
                <div class="d-flex justify-content-between align-items-center gap-2 mt-2">
                    <div class="input-group input-group-sm" style="max-width: 150px">
                        <button class="btn btn-outline-secondary" type="button" data-cart-decrement aria-label="Reduce quantity">−</button>
                        <input class="form-control text-center" type="number" min="1" max="${stock ?? 10000}" data-cart-line-quantity aria-label="Cart quantity">
                        <button class="btn btn-outline-secondary" type="button" data-cart-increment aria-label="Increase quantity">+</button>
                    </div>
                    <span class="fw-semibold" data-cart-line-total></span>
                </div>`;
            row.querySelector('strong').textContent = product.dataset.productName;
            row.querySelector('[data-cart-remove]').addEventListener('click', () => {
                source.value = '0';
                render();
            });
            row.querySelector('[data-cart-decrement]').addEventListener('click', () => {
                const value = (parseInt(source.value || '0', 10) || 0) - 1;
                source.value = String(Math.max(0, value));
                render();
            });
            row.querySelector('[data-cart-increment]').addEventListener('click', () => {
                const value = (parseInt(source.value || '0', 10) || 0) + 1;
                source.value = String(stock === null ? value : Math.min(stock, value));
                render();
            });
            row.querySelector('[data-cart-line-quantity]').addEventListener('input', (event) => {
                const value = parseInt(event.target.value || '0', 10) || 0;
                source.value = String(stock === null ? value : Math.min(stock, value));
                render();
            });
            items.appendChild(row);
            rows.set(id, row);
        }
        source.dispatchEvent(new Event('input', {bubbles: true}));
        render();
    };
    form.querySelectorAll('[data-cart-add]').forEach((button) => button.addEventListener('click', () => addRow(button.closest('[data-pos-product]'))));
    form.querySelectorAll('[data-sale-quantity]').forEach((input) => {
        const product = input.closest('[data-pos-product]');
        if ((parseInt(input.value || '0', 10) || 0) > 0) addRow(product, 0);
        input.addEventListener('input', () => {
            if ((parseInt(input.value || '0', 10) || 0) > 0 && !rows.has(product.dataset.productId)) addRow(product, 0);
            render();
        });
    });
    const offlineWarning = document.querySelector('[data-offline-warning]');
    const updateOnlineState = () => offlineWarning?.classList.toggle('d-none', navigator.onLine);
    window.addEventListener('online', updateOnlineState);
    window.addEventListener('offline', updateOnlineState);
    updateOnlineState();
    render();
})();
</script>
@endpush
@endsection
