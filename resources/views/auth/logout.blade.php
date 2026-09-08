@extends('storefront.layout')
@section('content')<section class="receipt"><h1>Sign out</h1><form method="post" action="{{ route('backpack.auth.logout') }}">@csrf<button class="button">Sign out of Storex</button></form></section>@endsection
