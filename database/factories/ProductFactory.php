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
        return ['shop_id' => Shop::factory(), 'name' => fake()->words(3, true), 'selling_price' => '12.50', 'sale_price' => null, 'cost_price' => '8.00', 'quantity' => 20, 'barcode' => fake()->unique()->ean13(), 'status' => 'approved', 'visibility' => 'published'];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved']);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['visibility' => 'draft']);
    }
}
