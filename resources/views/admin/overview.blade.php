@extends(backpack_view('blank'))

@section('header')
<section class="container-fluid">
    <h1>Platform overview</h1>
    <p class="text-muted">Review registered shops, their users, and products.</p>
</section>
@endsection

@section('content')
@include('admin.messages')
<div class="row g-4">
    @foreach([
        'shops' => ['Registered shops', 'la-store', '#1d4ed8'],
        'pending_shops' => ['Shops pending approval', 'la-hourglass-half', '#92400e'],
        'users' => ['Registered users', 'la-users', '#7e22ce'],
        'products' => ['Products', 'la-box', '#047857'],
    ] as $key => [$label, $icon, $color])
        <div class="col-12 col-sm-6 col-lg">
            <div class="card h-100 text-white" style="background-color: {{ $color }};">
                <div class="card-body">
                    <div class="mb-2"><i class="la {{ $icon }}" aria-hidden="true"></i> {{ $label }}</div>
                    <div class="h1 mb-0 text-white">{{ number_format($stats[$key]) }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card mt-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h3 class="card-title">Shops needing attention <span class="badge bg-warning text-dark">{{ $shops->total() }}</span></h3>
            <p class="text-muted mb-0">Shops awaiting approval.</p>
        </div>
        <a class="btn btn-outline-primary" href="{{ route('shop.index') }}">View all shops</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead><tr><th>Shop</th><th>Location</th><th>Shop status</th><th>Products</th><th>Needs attention</th><th></th></tr></thead>
                <tbody>
                @forelse($shops as $shop)
                    <tr>
                        <td><a href="{{ route('workspace.show', $shop) }}">{{ $shop->name }}</a></td>
                        <td>{{ $shop->location }}</td>
                        <td>{{ ucfirst($shop->status) }}</td>
                        <td>{{ number_format($shop->products_count) }}</td>
                        <td>
                            @if($shop->status === 'pending')<span class="badge bg-warning text-dark">Shop pending approval</span>@endif
                        </td>
                        <td><a class="btn btn-sm btn-outline-primary" href="{{ route('workspace.show', $shop) }}">Review shop →</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-5"><h3>All caught up</h3><p class="text-muted mb-0">No shops are awaiting approval.</p></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $shops->links() }}
    </div>
</div>
@endsection
