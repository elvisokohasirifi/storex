@extends('storefront.layout')
@section('content')<section class="receipt">@if($message)<p class="notice">{{ $message }}</p>@endif @include('storefront.receipt-details')<a class="button" href="{{ route('shops.show', $order->shop->slug) }}">Back to shop ↗</a></section>@endsection
