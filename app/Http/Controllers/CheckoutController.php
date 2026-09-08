<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Shop;
use App\Services\PaystackService;
use App\Services\SalesService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function store(Request $request, Shop $shop, SalesService $sales, PaystackService $paystack): RedirectResponse
    {
        abort_unless($shop->status === 'approved', 404);
        if (! $shop->paystack_secret_key) {
            throw ValidationException::withMessages(['payment' => 'This shop has not enabled online payment yet.']);
        }
        $rules = self::rules();
        $rules['customer_email'] = ['required', 'email', 'max:255'];
        $data = $request->validate($rules);
        $items = array_filter($data['items'], fn ($quantity) => $quantity > 0);
        $order = $sales->create($shop, $data, $items);
        try {
            return redirect()->away($paystack->initialize($order));
        } catch (ValidationException|ConnectionException $exception) {
            $order->update(['status' => 'cancelled']);
            throw ValidationException::withMessages(['payment' => 'Payment could not be started. Please try again.']);
        }
    }

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:150'], 'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50', 'regex:/^\+?[0-9][0-9\s().-]{5,48}[0-9]$/'], 'delivery_address' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:100'], 'items.*' => ['required', 'integer', 'min:0', 'max:10000'],
        ];
    }

    public function callback(Request $request, PaystackService $paystack): View
    {
        $data = $request->validate(['reference' => ['required', 'uuid']]);
        $order = Order::where('reference', $data['reference'])->where('channel', 'paystack')->firstOrFail();
        $message = null;
        try {
            $order = $paystack->verify($order);
        } catch (ValidationException|ConnectionException $exception) {
            $message = 'Your payment is not confirmed yet. Refresh this page to check again.';
        }

        return view('storefront.receipt', ['order' => $order->load('items', 'shop'), 'message' => $message]);
    }

    public function webhook(Request $request, Shop $shop, PaystackService $paystack): JsonResponse
    {
        $order = $shop->orders()->where('reference', (string) $request->input('data.reference'))->where('channel', 'paystack')->first();
        $key = $order?->payment_secret ?: $shop->paystack_secret_key;
        abort_unless($key && hash_equals(hash_hmac('sha512', $request->getContent(), $key), (string) $request->header('x-paystack-signature')), 401);
        if ($request->input('event') === 'charge.success' && $order) {
            $paystack->verify($order);
        }

        return response()->json(['received' => true]);
    }
}
