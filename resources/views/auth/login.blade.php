@extends('storefront.layout')
@section('content')
@php($phoneLogin = old('method') === 'phone')
<section class="receipt">
    <p class="eyebrow">WELCOME BACK</p>
    <h1>Your shop starts here.</h1>
    <p>Sign in with your email and password, phone and PIN, or Google.</p>
    <div class="login-box">
        <div class="login-tabs" role="tablist" aria-label="Login method">
            <button type="button" id="email-tab" role="tab" aria-controls="email-panel" aria-selected="{{ $phoneLogin ? 'false' : 'true' }}" tabindex="{{ $phoneLogin ? '-1' : '0' }}">Email &amp; password</button>
            <button type="button" id="phone-tab" role="tab" aria-controls="phone-panel" aria-selected="{{ $phoneLogin ? 'true' : 'false' }}" tabindex="{{ $phoneLogin ? '0' : '-1' }}">Phone &amp; PIN</button>
        </div>
        <div id="email-panel" role="tabpanel" aria-labelledby="email-tab" @if($phoneLogin) hidden @endif>
            <form method="post" class="checkout-fields" action="{{ route('backpack.auth.login') }}">
                @csrf
                <input type="hidden" name="method" value="email">
                <label>Email<input type="email" name="email" required autocomplete="username" value="{{ old('email') }}"></label>
                <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
                <button class="button">Sign in with email</button>
            </form>
        </div>
        <div id="phone-panel" role="tabpanel" aria-labelledby="phone-tab" @unless($phoneLogin) hidden @endunless>
            <form method="post" class="checkout-fields" action="{{ route('backpack.auth.login') }}">
                @csrf
                <input type="hidden" name="method" value="phone">
                <label>Phone with country code<input type="tel" name="phone" required placeholder="+233241234567" autocomplete="username" value="{{ old('phone') }}"></label>
                <label>6-digit PIN<input type="password" name="pin" required inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="current-password"></label>
                <button class="button">Sign in with PIN</button>
            </form>
        </div>
    </div>
    <p style="margin-top:24px"><a class="button secondary" href="{{ route('google.redirect') }}">Continue with Google ↗</a></p>
    <p><a href="{{ route('backpack.auth.password.reset') }}">Forgot your password?</a> · <a href="{{ route('backpack.auth.register') }}">Create an account</a></p>
</section>
<script>
(() => {
    const tabs = Array.from(document.querySelectorAll('.login-tabs [role="tab"]'));
    const activate = (selected) => {
        tabs.forEach((tab) => {
            const active = tab === selected;
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;
            document.getElementById(tab.getAttribute('aria-controls')).hidden = !active;
        });
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activate(tab));
        tab.addEventListener('keydown', (event) => {
            let next;
            if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
            else if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
            else if (event.key === 'Home') next = 0;
            else if (event.key === 'End') next = tabs.length - 1;
            else return;
            event.preventDefault();
            activate(tabs[next]);
            tabs[next].focus();
        });
    });
})();
</script>
@endsection