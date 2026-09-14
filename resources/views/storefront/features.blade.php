@extends('storefront.layout')
@section('title', 'Features — Storex')
@section('content')
<section class="feature-hero">
    <p class="eyebrow">STORE MANAGEMENT, SALES, AND STOREFRONTS</p>
    <h1>Everything small shops need to sell and stay organized.</h1>
    <p>Storex combines a public storefront, point of sale, inventory, payments, finance records, and moderation tools so small shops can run online and in person from one place.</p>
    <div class="feature-hero-actions"><a class="button" href="{{ backpack_url('register') }}">Start your shop ↗</a><a class="button secondary" href="{{ route('home') }}#shops">Explore shops</a></div>
</section>
@include('storefront.partials.features', ['features' => $features])
<section class="owner-banner"><div><p class="eyebrow">READY WHEN YOUR SHOP IS</p><h2>Set up products, accept sales, and keep stock moving.</h2><p>Start with your shop profile, then add users, products, payment methods, and inventory controls as your shop grows.</p></div><a class="button" href="{{ backpack_url('register') }}">Open your shop ↗</a></section>
@endsection
