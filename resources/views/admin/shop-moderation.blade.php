@extends(backpack_view('blank'))
@section('header')
<section class="container-fluid"><h1>{{ $shop->name }}</h1><p class="text-muted">{{ $shop->location }} · {{ ucfirst($shop->status) }}</p></section>
@endsection
@section('content')
<div class="card"><div class="card-body"><h3>Shop details</h3><p style="white-space: pre-line">{{ $shop->description }}</p><p>{{ $shop->contacts }} · {{ $shop->email }}</p><a class="btn btn-outline-primary" href="{{ route('shop.show', $shop) }}">Review shop</a> <a class="btn btn-primary" href="{{ route('moderation.index') }}">Approvals & moderation</a></div></div>
<div class="card"><div class="card-body"><h3>Shop users</h3><div class="table-responsive"><table class="table"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th></tr></thead><tbody>
<tr><td>{{ $shop->owner->name }}</td><td>{{ $shop->owner->email }}</td><td>{{ $shop->owner->phone ?: '—' }}</td><td>Owner</td></tr>
@foreach($members as $member)<tr><td>{{ $member->user->name }}</td><td>{{ $member->user->email }}</td><td>{{ $member->user->phone ?: '—' }}</td><td>{{ ucfirst($member->role) }}</td></tr>@endforeach
</tbody></table></div></div></div>
<div class="card"><div class="card-body"><h3>Inventory moderation</h3>
<form method="get" class="d-flex gap-2 mb-3"><input class="form-control" name="q" value="{{ $search }}" placeholder="Product name or barcode" aria-label="Search inventory"><button class="btn btn-outline-primary">Search</button></form>
<div class="table-responsive"><table class="table"><thead><tr><th>Product</th><th>Barcode</th><th>Selling price</th><th>Stock</th><th>Status</th></tr></thead><tbody>
@forelse($products as $product)<tr><td><a href="{{ route('product.show', $product) }}">{{ $product->name }}</a></td><td>{{ $product->barcode ?: '—' }}</td><td>{{ $shop->currency }} {{ $product->selling_price }}</td><td>{{ $product->quantity ?? 'Untracked' }}</td><td>{{ ucfirst($product->status) }}</td></tr>
@empty<tr><td colspan="5">No products to review.</td></tr>@endforelse
</tbody></table></div>{{ $products->links() }}</div></div>
@endsection
