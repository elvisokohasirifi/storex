<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PaystackService
{
    public function initialize(Order $order): string
    {
        $response = Http::withToken($order->payment_secret)->acceptJson()->connectTimeout(5)->timeout(20)
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $order->customer_email, 'amount' => $order->total, 'currency' => $order->currency,
                'reference' => $order->reference,
                'callback_url' => route('checkout.callback', ['reference' => $order->reference]),
            ]);
        $url = $response->json('data.authorization_url');
        if (! $response->successful() || $response->json('status') !== true || ! is_string($url)
            || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'checkout.paystack.com') {
            throw ValidationException::withMessages(['payment' => 'Payment could not be started. Please try again shortly.']);
        }
        $order->update(['payment_url' => $url]);

        return $url;
    }

    public function verify(Order $order): Order
    {
        $response = Http::withToken($order->payment_secret)->acceptJson()->connectTimeout(5)->timeout(20)
            ->get('https://api.paystack.co/transaction/verify/'.rawurlencode($order->reference));
        if (! $response->successful() || $response->json('status') !== true || ! is_array($response->json('data'))) {
            throw ValidationException::withMessages(['payment' => 'Unable to verify payment yet. Please try again.']);
        }

        return app(SalesService::class)->settle($order, $response->json('data'));
    }
}
