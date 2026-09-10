<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductVariant> */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return ['product_id' => Product::factory(), 'name' => fake()->word(), 'selling_price' => null, 'cost_price' => null, 'quantity' => null, 'reorder_level' => 10];
    }
}
