@extends(backpack_view('blank'))
@section('header')
<section class="container-fluid"><h1>{{ $shop->name }}</h1><p class="text-muted">{{ $shop->location }} · {{ ucfirst($shop->status) }}</p></section>
@endsection
@section('content')
@include('admin.messages')
<div class="card mb-4"><div class="card-body"><h3>Shop details</h3>
@include('admin.shop-details')
<form method="post" action="{{ route('moderation.store') }}">
    @csrf
    <input type="hidden" name="type" value="shops">
    <input type="hidden" name="ids[]" value="{{ $shop->id }}">
    <input type="hidden" name="status" value="approved">
    <button class="btn btn-success" @disabled($shop->status === 'approved')>{{ $shop->status === 'approved' ? 'Shop approved' : 'Approve shop and products' }}</button>
</form></div></div>
<div class="card mb-4"><div class="card-body"><h3>Shop users</h3><div class="table-responsive"><table class="table"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th></tr></thead><tbody>
<tr><td>{{ $shop->owner->name }}</td><td>{{ $shop->owner->email }}</td><td>{{ $shop->owner->phone ?: '—' }}</td><td>Owner</td></tr>
@foreach($members as $member)<tr><td>{{ $member->user->name }}</td><td>{{ $member->user->email }}</td><td>{{ $member->user->phone ?: '—' }}</td><td>{{ ucfirst($member->role) }}</td></tr>@endforeach
</tbody></table></div></div></div>
<div class="card mb-4"><div class="card-body"><h3>Products</h3>
<p class="text-muted">Products and product uploads become public when this shop is approved.</p>
<form method="get" class="d-flex gap-2 mb-3"><input class="form-control" name="q" value="{{ $search }}" placeholder="Product name or barcode" aria-label="Search products"><button class="btn btn-outline-primary">Search</button></form>
<div class="table-responsive"><table class="table"><thead><tr><th>Product</th><th>Barcode</th><th>Visibility</th><th>Selling price</th><th>Stock</th></tr></thead><tbody>
@forelse($products as $product)<tr><td><a href="{{ route('product.show', $product) }}">{{ $product->name }}</a></td><td>{{ $product->barcode ?: '—' }}</td><td>{{ ucfirst($product->visibility) }}</td><td>{{ $shop->currency }} {{ $product->selling_price }}</td><td>{{ $product->quantity ?? 'Untracked' }}</td></tr>
@empty<tr><td colspan="5">No products to review.</td></tr>@endforelse
</tbody></table></div>{{ $products->links() }}</div></div>
@endsection
