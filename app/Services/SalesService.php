<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalesService
{
    public static function minorUnits(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public function available(Product $product, ?string $exceptOrder = null): ?int
    {
        if ($product->quantity === null) {
            return null;
        }
        $reserved = OrderItem::where('product_id', $product->id)->whereHas('order', fn ($query) => $query
            ->where('status', 'pending')->where('expires_at', '>', now())
            ->when($exceptOrder, fn ($query) => $query->where('id', '!=', $exceptOrder)))->sum('quantity');

        return max(0, $product->quantity - (int) $reserved);
    }

    /**
     * @param  array<string, mixed>  $customer
     * @param  array<string, int>  $items
     */
    public function create(Shop $shop, array $customer, array $items, ?User $seller = null): Order
    {
        return DB::transaction(function () use ($shop, $customer, $items, $seller) {
            $shop = Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
            if ($shop->status !== 'approved') {
                throw ValidationException::withMessages(['shop' => 'This shop is not currently accepting sales.']);
            }
            if ($seller && ! $seller->manages($shop)) {
                abort(403);
            }
            $products = $shop->products()->whereIn('id', array_keys($items))->orderBy('id')->lockForUpdate()->get();
            if ($products->count() !== count($items) || $products->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Choose products from this shop.']);
            }
            $total = 0;
            foreach ($products as $product) {
                $quantity = $items[$product->id];
                $available = $this->available($product);
                if ($quantity < 1 || $product->status !== 'approved' || ($available !== null && $available < $quantity)) {
                    throw ValidationException::withMessages(['items' => $product->name.' is unavailable in the requested quantity.']);
                }
                $total += self::minorUnits($product->selling_price) * $quantity;
            }
            $order = $shop->orders()->create([
                'seller_id' => $seller?->id, 'reference' => (string) Str::uuid(),
                'customer_name' => $customer['customer_name'], 'customer_email' => $customer['customer_email'],
                'customer_phone' => $customer['customer_phone'] ?? null, 'delivery_address' => $customer['delivery_address'] ?? null,
                'currency' => $shop->currency, 'total' => $total, 'channel' => $seller ? 'cash' : 'paystack',
                'status' => $seller ? 'paid' : 'pending', 'paid_at' => $seller ? now() : null,
                'expires_at' => $seller ? null : now()->addMinutes(15), 'payment_secret' => $seller ? null : $shop->paystack_secret_key,
            ]);
            foreach ($products as $product) {
                $quantity = $items[$product->id];
                $order->items()->create([
                    'product_id' => $product->id, 'name' => $product->name, 'quantity' => $quantity,
                    'unit_price' => self::minorUnits($product->selling_price),
                    'unit_cost' => $product->cost_price === null ? null : self::minorUnits($product->cost_price),
                    'tracks_stock' => $product->quantity !== null,
                ]);
                if ($seller && $product->quantity !== null) {
                    $product->decrement('quantity', $quantity);
                }
            }

            return $order;
        }, 3);
    }

    /** @param array<string, mixed> $payment */
    public function settle(Order $order, array $payment): Order
    {
        return DB::transaction(function () use ($order, $payment) {
            $shop = Shop::whereKey($order->shop_id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (($payment['status'] ?? null) !== 'success'
                || ($payment['reference'] ?? null) !== $order->reference
                || (string) ($payment['amount'] ?? '') !== (string) $order->total
                || ($payment['currency'] ?? null) !== $order->currency
                || strcasecmp($payment['customer']['email'] ?? '', $order->customer_email) !== 0) {
                throw ValidationException::withMessages(['payment' => 'Payment verification did not match this order.']);
            }
            if (in_array($order->status, ['paid', 'paid_review'], true)) {
                return $order;
            }
            $items = $order->items()->orderBy('product_id')->get();
            $products = Product::whereIn('id', $items->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $canFulfil = $shop->status === 'approved';
            foreach ($items as $item) {
                $product = $products->get($item->product_id);
                $available = $product ? $this->available($product, $order->id) : 0;
                if (! $product || $product->status !== 'approved' || ($available !== null && $available < $item->quantity)) {
                    $canFulfil = false;
                }
            }
            if ($canFulfil) {
                foreach ($items as $item) {
                    $product = $products[$item->product_id];
                    if ($product->quantity !== null) {
                        $product->decrement('quantity', $item->quantity);
                    }
                }
            }
            $order->update(['status' => $canFulfil ? 'paid' : 'paid_review', 'paid_at' => now()]);

            return $order;
        }, 3);
    }
}
