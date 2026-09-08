<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect()->route('backpack.auth.login')->withErrors(['google' => 'Google sign-in is not configured yet. Use email or phone to sign in.']);
        }
        $state = Str::random(64);
        $verifier = Str::random(96);
        $request->session()->put('google_oauth', ['state' => $state, 'verifier' => $verifier, 'expires' => now()->addMinutes(10)->timestamp, 'user_id' => backpack_user()?->id]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => route('google.callback'), 'response_type' => 'code', 'scope' => 'openid email profile',
            'state' => $state, 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256', 'prompt' => 'select_account',
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        $flow = $request->session()->pull('google_oauth');
        if (! is_array($flow) || ! hash_equals($flow['state'], (string) $request->query('state')) || $flow['expires'] < now()->timestamp || ! $request->filled('code') || $flow['user_id'] !== backpack_user()?->id) {
            return redirect()->route('backpack.auth.login')->withErrors(['google' => 'Google sign-in expired or was cancelled. Please try again.']);
        }
        try {
            $token = Http::asForm()->connectTimeout(5)->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google.client_id'), 'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => route('google.callback'), 'code' => $request->query('code'),
                'code_verifier' => $flow['verifier'], 'grant_type' => 'authorization_code',
            ]);
            if (! $token->successful() || ! is_string($token->json('access_token'))) {
                return $this->failed();
            }
            $profile = Http::withToken($token->json('access_token'))->connectTimeout(5)->timeout(15)->get('https://openidconnect.googleapis.com/v1/userinfo');
        } catch (ConnectionException $exception) {
            return $this->failed();
        }
        if (! $profile->successful() || $profile->json('email_verified') !== true || ! is_string($profile->json('sub')) || ! filter_var($profile->json('email'), FILTER_VALIDATE_EMAIL)) {
            return $this->failed();
        }
        $googleId = $profile->json('sub');
        $email = Str::lower($profile->json('email'));
        $user = User::where('google_id', $googleId)->first();
        if ($flow['user_id']) {
            $current = backpack_user();
            if (($user && $user->id !== $current->id) || Str::lower($current->email) !== $email) {
                return $this->failed();
            }
            $current->forceFill(['google_id' => $googleId])->save();

            return redirect()->route('account.security')->with('success', 'Google sign-in connected.');
        }
        if (! $user) {
            if (User::where('email', $email)->exists()) {
                return redirect()->route('backpack.auth.login')->withErrors(['google' => 'Sign in to your existing account first, then connect Google in Login & security.']);
            }
            if (! config('backpack.base.registration_open')) {
                return $this->failed();
            }
            $user = User::create(['name' => Str::limit($profile->json('name') ?: 'Shop owner', 150, ''), 'email' => $email, 'password' => Str::random(64)]);
            $user->forceFill(['google_id' => $googleId, 'email_verified_at' => now()])->save();
        }
        backpack_auth()->login($user);
        $request->session()->regenerate();

        return redirect()->route('workspace.index');
    }

    private function failed(): RedirectResponse
    {
        return redirect()->route('backpack.auth.login')->withErrors(['google' => 'Google sign-in could not be completed. Please try again.']);
    }
}
