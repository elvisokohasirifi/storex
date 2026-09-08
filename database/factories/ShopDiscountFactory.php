<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\ShopDiscount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShopDiscount> */
class ShopDiscountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => 'Promo discount',
            'scope' => 'all_products',
            'type' => 'percentage',
            'value' => '10.00',
            'is_active' => true,
        ];
    }

    public function checkout(): static
    {
        return $this->state(fn () => ['scope' => 'checkout']);
    }

    public function fixed(string $value = '5.00'): static
    {
        return $this->state(fn () => ['type' => 'fixed', 'value' => $value]);
    }
}
