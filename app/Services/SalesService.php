<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopDiscount;
use App\Models\TillShift;
use App\Models\User;
use Illuminate\Support\Collection;
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

    /** @return Collection<int, ShopDiscount> */
    public function activeDiscounts(Shop $shop): Collection
    {
        return $shop->discounts()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Collection<int, ShopDiscount>  $discounts
     * @return array{base: int, final: int, discount: int, discount_name: ?string}
     */
    public function priceFor(Product $product, Collection $discounts): array
    {
        $basePrice = self::minorUnits($product->baseSaleAmount());
        $bestDiscount = null;
        $bestDiscountAmount = 0;

        foreach ($discounts as $discount) {
            if (! $this->matchesProductDiscount($product, $discount)) {
                continue;
            }
            $amount = $this->discountAmount($basePrice, $discount);
            if ($amount > $bestDiscountAmount) {
                $bestDiscount = $discount;
                $bestDiscountAmount = $amount;
            }
        }

        return [
            'base' => $basePrice,
            'final' => max(0, $basePrice - $bestDiscountAmount),
            'discount' => $bestDiscountAmount,
            'discount_name' => $bestDiscount?->name,
        ];
    }

    /**
     * @param  Collection<int, ShopDiscount>  $discounts
     * @return array{amount: int, name: ?string}
     */
    public function checkoutDiscountFor(int $subtotal, Collection $discounts): array
    {
        $bestDiscount = null;
        $bestDiscountAmount = 0;

        foreach ($discounts->where('scope', 'checkout') as $discount) {
            $amount = $this->discountAmount($subtotal, $discount);
            if ($amount > $bestDiscountAmount) {
                $bestDiscount = $discount;
                $bestDiscountAmount = $amount;
            }
        }

        return ['amount' => min($subtotal, $bestDiscountAmount), 'name' => $bestDiscount?->name];
    }

    private function matchesProductDiscount(Product $product, ShopDiscount $discount): bool
    {
        return match ($discount->scope) {
            'all_products' => true,
            'category' => $discount->category_id !== null && $discount->category_id === $product->category_id,
            'brand' => $discount->brand_id !== null && $discount->brand_id === $product->brand_id,
            default => false,
        };
    }

    private function discountAmount(int $amount, ShopDiscount $discount): int
    {
        if ($discount->type === 'percentage') {
            return (int) floor($amount * min(100, (float) $discount->value) / 100);
        }

        return min($amount, self::minorUnits(number_format((float) $discount->value, 2, '.', '')));
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
            $discounts = $this->activeDiscounts($shop);
            $total = 0;
            $lineDiscountTotal = 0;
            foreach ($products as $product) {
                $quantity = $items[$product->id];
                $available = $this->available($product);
                if ($quantity < 1 || (! $seller && $product->visibility !== 'published') || ($available !== null && $available < $quantity)) {
                    throw ValidationException::withMessages(['items' => $product->name.' is unavailable in the requested quantity.']);
                }
                $price = $this->priceFor($product, $discounts);
                $total += $price['final'] * $quantity;
                $lineDiscountTotal += $price['discount'] * $quantity;
            }
            $subtotal = $total + $lineDiscountTotal;
            $checkoutDiscount = $this->checkoutDiscountFor($total, $discounts);
            $total -= $checkoutDiscount['amount'];
            $discountTotal = $lineDiscountTotal + $checkoutDiscount['amount'];
            $discountName = match (true) {
                $lineDiscountTotal > 0 && $checkoutDiscount['name'] !== null => 'Product promotions + '.$checkoutDiscount['name'],
                $lineDiscountTotal > 0 => 'Product promotions',
                default => $checkoutDiscount['name'],
            };
            $order = $shop->orders()->create([
                'seller_id' => $seller?->id, 'customer_id' => $this->customerFor($shop, $customer)?->id, 'till_shift_id' => $seller ? $this->openShiftFor($shop, $seller)?->id : null, 'reference' => (string) Str::uuid(),
                'receipt_number' => $this->receiptNumber(),
                'customer_name' => $customer['customer_name'], 'customer_email' => $customer['customer_email'] ?? null,
                'customer_phone' => $customer['customer_phone'] ?? null, 'delivery_address' => $customer['delivery_address'] ?? null,
                'currency' => $shop->currency, 'subtotal' => $subtotal, 'discount_total' => $discountTotal, 'discount_name' => $discountName,
                'total' => $total, 'channel' => $this->channelFor($customer, $seller), 'payment_method' => $this->paymentMethodFor($customer, $seller),
                'status' => $seller ? 'paid' : 'pending', 'paid_at' => $seller ? now() : null,
                'expires_at' => $seller ? null : now()->addMinutes(15), 'payment_secret' => $this->paymentMethodFor($customer, $seller) === 'paystack' ? $shop->paystack_secret_key : null,
            ]);
            foreach ($products as $product) {
                $quantity = $items[$product->id];
                $price = $this->priceFor($product, $discounts);
                $order->items()->create([
                    'product_id' => $product->id, 'name' => $product->name, 'quantity' => $quantity,
                    'unit_price' => $price['final'],
                    'unit_cost' => $product->cost_price === null ? null : self::minorUnits($product->cost_price),
                    'tracks_stock' => $product->quantity !== null,
                ]);
                if ($seller && $product->quantity !== null) {
                    $this->recordSaleMovement($shop, $product, $order, $quantity, $seller);
                }
            }

            AuditLog::record($shop, 'sale.created', $order, ['total' => $order->total, 'payment_method' => $order->payment_method]);

            return $order;
        }, 3);
    }

    public function cancel(Order $order, string $reason): Order
    {
        return $this->voidOrder($order, 'cancelled', $reason);
    }

    public function refund(Order $order, string $reason): Order
    {
        return $this->voidOrder($order, 'refunded', $reason);
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
                || $order->customer_email === null
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
                if (! $product || $product->visibility !== 'published' || ($available !== null && $available < $item->quantity)) {
                    $canFulfil = false;
                }
            }
            if ($canFulfil) {
                foreach ($items as $item) {
                    $product = $products[$item->product_id];
                    if ($product->quantity !== null) {
                        $this->recordSaleMovement($shop, $product, $order, $item->quantity);
                    }
                }
            }
            $order->update(['status' => $canFulfil ? 'paid' : 'paid_review', 'paid_at' => now()]);

            return $order;
        }, 3);
    }

    private function channelFor(array $customer, ?User $seller): string
    {
        if ($seller) {
            return 'cash';
        }

        return ($customer['payment_method'] ?? 'paystack') === 'paystack' ? 'paystack' : 'manual';
    }

    private function paymentMethodFor(array $customer, ?User $seller): string
    {
        if ($seller) {
            return $customer['payment_method'] ?? 'cash';
        }

        return $customer['payment_method'] ?? 'paystack';
    }

    private function customerFor(Shop $shop, array $customer): ?Customer
    {
        $phone = trim((string) ($customer['customer_phone'] ?? ''));
        if ($phone === '') {
            return null;
        }

        return $shop->customers()->updateOrCreate(
            ['phone' => $phone],
            ['name' => $customer['customer_name'], 'email' => $customer['customer_email'] ?? null, 'address' => $customer['delivery_address'] ?? null],
        );
    }

    private function openShiftFor(Shop $shop, User $seller): ?TillShift
    {
        return TillShift::where('shop_id', $shop->id)->where('user_id', $seller->id)->where('status', 'open')->latest('opened_at')->first();
    }

    private function receiptNumber(): string
    {
        return 'STX-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }

    private function voidOrder(Order $order, string $status, string $reason): Order
    {
        return DB::transaction(function () use ($order, $status, $reason): Order {
            $order = Order::with('items')->whereKey($order->id)->lockForUpdate()->firstOrFail();
            $shop = Shop::whereKey($order->shop_id)->lockForUpdate()->firstOrFail();
            if (! in_array($order->status, ['paid', 'paid_review', 'pending'], true)) {
                throw ValidationException::withMessages(['order' => 'This sale has already been cancelled or refunded.']);
            }

            if (in_array($order->status, ['paid', 'paid_review'], true)) {
                $products = Product::whereIn('id', $order->items->pluck('product_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($order->items as $item) {
                    $product = $products->get($item->product_id);
                    if (! $product || ! $item->tracks_stock) {
                        continue;
                    }
                    $product->increment('quantity', $item->quantity);
                    if ($shop->enable_inventory_management) {
                        InventoryMovement::create([
                            'shop_id' => $shop->id,
                            'product_id' => $product->id,
                            'type' => 'return',
                            'quantity' => $item->quantity,
                            'reference_type' => Order::class,
                            'reference_id' => $order->id,
                            'reason' => ucfirst($status).' reversal: '.$reason,
                            'user_id' => backpack_user()?->id,
                        ]);
                    }
                }
            }

            $order->update([
                'status' => $status,
                'cancelled_at' => $status === 'cancelled' ? now() : $order->cancelled_at,
                'refunded_at' => $status === 'refunded' ? now() : $order->refunded_at,
                'status_reason' => $reason,
            ]);
            AuditLog::record($shop, 'sale.'.$status, $order, ['reason' => $reason]);

            return $order;
        }, 3);
    }

    private function recordSaleMovement(Shop $shop, Product $product, Order $order, int $quantity, ?User $seller = null): void
    {
        $product->decrement('quantity', $quantity);

        if (! $shop->enable_inventory_management) {
            return;
        }

        InventoryMovement::create([
            'shop_id' => $shop->id,
            'product_id' => $product->id,
            'type' => 'sale',
            'quantity' => -$quantity,
            'reference_type' => Order::class,
            'reference_id' => $order->id,
            'reason' => 'Sale #'.$order->reference,
            'user_id' => $seller?->id,
        ]);
    }
}
