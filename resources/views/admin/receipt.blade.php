@extends(backpack_view('blank'))
@section('content')<div class="card"><div class="card-body p-4">@include('storefront.receipt-details')@include('admin.receipt-sharing')<a class="btn btn-primary mt-4" href="{{ route('workspace.till', $order->shop) }}">Back to till</a></div></div>@endsection
