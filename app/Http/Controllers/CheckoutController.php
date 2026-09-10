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

        if (! $request->filled('payment_method')) {
            $request->merge(['payment_method' => $shop->paystack_secret_key ? 'paystack' : 'cash']);
        }

        $rules = self::rules();
        $rules['payment_method'] = ['required', 'string', 'in:cash,momo,paystack'];
        if ($request->input('payment_method') === 'paystack') {
            $rules['customer_email'] = ['required', 'email', 'max:255'];
        }

        $data = $request->validate($rules);
        $items = array_filter($data['items'], fn ($quantity) => $quantity > 0);
        if (! $items) {
            throw ValidationException::withMessages(['items' => 'Add at least one product to your cart.']);
        }

        if ($data['payment_method'] === 'paystack') {
            if (! $shop->paystack_secret_key) {
                throw ValidationException::withMessages(['payment_method' => 'This shop has not enabled Paystack yet.']);
            }

            $order = $sales->create($shop, $data, $items);
            try {
                $request->session()->forget('storefront_cart_'.$shop->id);

                return redirect()->away($paystack->initialize($order));
            } catch (ValidationException|ConnectionException $exception) {
                $order->update(['status' => 'cancelled']);
                throw ValidationException::withMessages(['payment' => 'Payment could not be started. Please try again.']);
            }
        }

        if ($shop->paystack_secret_key) {
            throw ValidationException::withMessages(['payment_method' => 'Choose Paystack for online checkout.']);
        }

        if ($data['payment_method'] === 'momo' && ! $shop->momo_number) {
            throw ValidationException::withMessages(['payment_method' => 'This shop has not added mobile money details yet.']);
        }

        $order = $sales->create($shop, $data, $items);
        $request->session()->forget('storefront_cart_'.$shop->id);

        return redirect()->route('checkout.callback', ['reference' => $order->reference]);
    }

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:50', 'regex:/^\+?[0-9][0-9\s().-]{5,48}[0-9]$/'],
            'delivery_address' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['nullable', 'string', 'in:cash,momo,card,bank_transfer,paystack,other'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*' => ['required', 'integer', 'min:0', 'max:10000'],
        ];
    }

    public function callback(Request $request, PaystackService $paystack): View
    {
        $data = $request->validate(['reference' => ['required', 'uuid']]);
        $order = Order::where('reference', $data['reference'])->firstOrFail();
        $message = null;

        if ($order->channel === 'paystack') {
            try {
                $order = $paystack->verify($order);
            } catch (ValidationException|ConnectionException $exception) {
                $message = 'Your payment is not confirmed yet. Refresh this page to check again.';
            }
        } elseif ($order->payment_method === 'momo') {
            $message = 'Your order is reserved. Send mobile money to '.$order->shop->momo_account_name.' on '.$order->shop->momo_number.' and use reference '.$order->reference.'.';
        } else {
            $message = 'Your order is reserved. Pay with cash when you collect or receive your items.';
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
