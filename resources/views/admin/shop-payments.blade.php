@extends('admin.shop-page')
@section('page_title', 'Payment credentials')
@section('shop_content')
<div class="card mb-4">
    <div class="card-body">
        <h3>Payment credentials</h3>
        <p class="text-muted">Paystack public key: {{ $shop->paystack_public_key ? 'Saved' : 'Not set' }} · Secret key: {{ $shop->paystack_secret_key ? 'Saved' : 'Not set' }} · Mobile money: {{ $shop->momo_number ? 'Saved' : 'Not set' }}</p>
        <p>Payments go to your Paystack or mobile money account. Leave key fields blank to keep existing Paystack credentials. Keys are never displayed.</p>
        <form method="post" action="{{ route('workspace.credentials', $shop) }}">
            @csrf
            <label class="form-label w-100">Public key<input class="form-control" name="public_key" type="password" autocomplete="new-password" placeholder="pk_test_... or pk_live_..."></label>
            <label class="form-label w-100">Secret key<input class="form-control" name="secret_key" type="password" autocomplete="new-password" placeholder="sk_test_... or sk_live_..."></label>
            <label class="form-label w-100">Mobile money number<input class="form-control" name="momo_number" value="{{ old('momo_number', $shop->momo_number) }}" placeholder="+233241234567"></label>
            <label class="form-label w-100">Mobile money account name<input class="form-control" name="momo_account_name" value="{{ old('momo_account_name', $shop->momo_account_name) }}" placeholder="Registered account name"></label>
            <label class="form-label w-100">Currency<select class="form-select" name="currency">@foreach(['GHS','NGN','ZAR','KES','XOF','EGP'] as $currency)<option @selected($shop->currency === $currency)>{{ $currency }}</option>@endforeach</select></label>
            <p class="small">Use a currency enabled on your Paystack account. Set this webhook URL in your Paystack dashboard:</p>
            <code class="d-block text-break mb-3">{{ route('checkout.webhook', $shop) }}</code>
            <button class="btn btn-primary">Save payment settings</button>
        </form>
    </div>
</div>
@endsection
