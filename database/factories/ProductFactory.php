<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return ['shop_id' => Shop::factory(), 'name' => fake()->words(3, true), 'selling_price' => '12.50', 'cost_price' => '8.00', 'quantity' => 20, 'barcode' => fake()->unique()->ean13(), 'status' => 'pending'];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved']);
    }
}
