@extends('admin.shop-page')
@section('page_title', 'Till / point of sale')
@section('shop_content')
@unless($canSell)<div class="alert alert-warning">Sales are available once this shop is approved.</div>@endunless
<div class="card mb-4"><div class="card-header"><h3 class="card-title">Till / point of sale</h3></div><div class="card-body">
<form method="get" class="d-flex flex-column flex-md-row gap-2 mb-3" data-barcode-pos data-barcode-target="q" data-submit-on-scan>
    <input class="form-control" name="q" value="{{ $search }}" placeholder="Search product name or scan barcode" aria-label="Search products">
    <button class="btn btn-outline-primary" type="submit">Search</button>
    <button class="btn btn-outline-primary" type="button" data-barcode-scan><i class="la la-barcode" aria-hidden="true"></i> Scan barcode</button>
    <p class="small text-muted mb-0 align-self-md-center" data-barcode-status role="status" aria-live="polite"></p>
</form>
@if($canSell)<form method="post" action="{{ route('workspace.sale', $shop) }}">@csrf @endif
<div class="table-responsive"><table class="table table-hover"><thead><tr><th>Product</th><th>Barcode</th><th>Visibility</th><th>Price</th><th>Stock</th>@if($canSell)<th>Sell quantity</th>@endif</tr></thead><tbody>
@forelse($products as $product)@php($price = $productPrices[$product->id])<tr @if($product->barcode) data-product-barcode="{{ $product->barcode }}" @endif><td><a href="{{ route('product.show', $product) }}">{{ $product->name }}</a></td><td>{{ $product->barcode ?: '—' }}</td><td>{{ ucfirst($product->visibility) }}</td><td>{{ $shop->currency }} {{ number_format($price['final'] / 100, 2) }}@if($price['final'] < (int) round((float) $product->selling_price * 100)) <span class="text-muted"><s>{{ $shop->currency }} {{ number_format((float) $product->selling_price, 2) }}</s></span>@endif</td><td>{{ $product->quantity ?? 'Untracked' }}</td>@if($canSell)<td><input style="max-width:100px" class="form-control" type="number" min="0" max="10000" value="{{ old('items.'.$product->id, 0) }}" name="items[{{ $product->id }}]" aria-label="Quantity for {{ $product->name }}" data-sale-quantity></td>@endif</tr>
@empty<tr><td colspan="{{ $canSell ? 6 : 5 }}">No products match your search. Add products from the Products page.</td></tr>@endforelse</tbody></table></div>{{ $products->links() }}
@if($canSell)
<div class="row mt-3"><div class="col-md-4"><label class="form-label">Customer name<input class="form-control" name="customer_name" required maxlength="150" value="{{ old('customer_name', 'Walk-in customer') }}"></label></div><div class="col-md-4"><label class="form-label">Customer email (optional)<input class="form-control" type="email" name="customer_email" value="{{ old('customer_email') }}"></label></div><div class="col-md-4"><label class="form-label">Phone number<input class="form-control" type="tel" name="customer_phone" required maxlength="50" value="{{ old('customer_phone') }}"></label></div></div>
<button class="btn btn-primary mt-2" type="submit">Record cash sale & issue receipt</button><p class="text-muted mt-2">Record a cash sale only after receiving payment. Stock is checked on submission.</p></form>@endif
</div></div>

@push('after_scripts')
<script src="{{ asset('barcode-scanner.js') }}"></script>
@endpush
@endsection
