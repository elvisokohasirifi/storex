<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function loginForm(): View|RedirectResponse
    {
        if (backpack_auth()->check()) {
            return redirect()->route('workspace.index');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['method' => ['required', Rule::in(['email', 'phone'])],
            'email' => ['required_if:method,email', 'nullable', 'email'], 'password' => ['required_if:method,email', 'nullable', 'string'],
            'phone' => ['required_if:method,phone', 'nullable', 'regex:/^\\+[1-9][0-9]{7,14}$/'], 'pin' => ['required_if:method,phone', 'nullable', 'digits:6']]);
        $identifier = $data['method'] === 'phone' ? $data['phone'] : Str::lower($data['email']);
        $key = 'signin:'.hash('sha256', $identifier);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['login' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.']);
        }
        RateLimiter::hit($key, 300);
        $user = User::where($data['method'] === 'phone' ? 'phone' : 'email', $identifier)->first();
        $hash = $data['method'] === 'phone' ? $user?->pin : $user?->password;
        $secret = $data['method'] === 'phone' ? $data['pin'] : $data['password'];
        if (! $hash || ! Hash::check($secret, $hash)) {
            throw ValidationException::withMessages(['login' => 'The login details are incorrect.']);
        }
        RateLimiter::clear($key);
        backpack_auth()->login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('workspace.index'));
    }

    public function registerForm(): View
    {
        abort_unless(config('backpack.base.registration_open'), 403);

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        abort_unless(config('backpack.base.registration_open'), 403);
        $request->merge(['email' => Str::lower((string) $request->input('email'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'max:255', 'confirmed'],
            'phone' => ['nullable', 'required_with:pin', 'regex:/^\\+[1-9][0-9]{7,14}$/', 'unique:users,phone'],
            'pin' => ['nullable', 'required_with:phone', 'digits:6', 'confirmed'],
        ]);
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
        $user->forceFill(['phone' => $data['phone'] ?? null, 'pin' => $data['pin'] ?? null])->save();
        backpack_auth()->login($user);
        $request->session()->regenerate();

        return redirect()->route('shop.create');
    }

    public function logout(Request $request): RedirectResponse
    {
        backpack_auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function security(): View
    {
        return view('admin.security');
    }

    public function updateSecurity(Request $request): RedirectResponse
    {
        $user = backpack_user();
        $data = $request->validate([
            'password' => ['required', 'string'],
            'phone' => ['nullable', 'required_with:pin', 'regex:/^\\+[1-9][0-9]{7,14}$/', Rule::unique('users')->ignore($user->id)],
            'pin' => ['nullable', 'required_with:phone', 'digits:6', 'confirmed'],
        ]);
        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Your current password is incorrect.']);
        }
        $user->forceFill(['phone' => $data['phone'] ?? null, 'pin' => $data['pin'] ?? null])->save();

        return back()->with('success', 'Phone login settings updated.');
    }
}
