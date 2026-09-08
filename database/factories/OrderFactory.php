<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return ['shop_id' => Shop::factory(), 'reference' => (string) Str::uuid(), 'customer_name' => 'Customer', 'customer_email' => 'customer@example.com', 'currency' => 'GHS', 'total' => 1250, 'channel' => 'paystack', 'status' => 'pending', 'expires_at' => now()->addMinutes(15)];
    }
}
