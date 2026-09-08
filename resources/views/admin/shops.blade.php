@extends(backpack_view('blank'))
@section('header')<section class="container-fluid"><h1>Store overview</h1><p class="text-muted">{{ backpack_user()->is_platform_admin ? 'Review registered shops, their users, and products.' : 'Your shops, products, and everyday sales in one place.' }}</p></section>@endsection
@section('content')
@include('admin.messages')
@if(!backpack_user()->is_platform_admin)<a class="btn btn-primary mb-4" href="{{ route('shop.create') }}"><i class="la la-plus"></i> Create a shop</a>@endif
<div class="row gx-4">@forelse($shops as $shop)<div class="col-md-6 col-xl-4"><div class="card mb-4"><div class="card-body"><span class="badge bg-secondary text-white mb-3">{{ ucfirst($shop->status) }}</span><h3>{{ $shop->name }}</h3><p class="text-muted">{{ $shop->location }} · {{ $shop->products_count }} products</p><a href="{{ route('workspace.show', $shop) }}" class="btn btn-outline-primary">Open workspace →</a></div></div></div>
@empty<div class="col-12"><div class="card mb-4"><div class="card-body p-5"><h2>Your first shop starts here.</h2><p>Create your shop, add products, and submit them for approval. Once approved, customers can shop at your unique storefront.</p></div></div></div>@endforelse</div>{{ $shops->links() }}
@endsection
