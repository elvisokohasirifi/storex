<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductCategory> */
class ProductCategoryFactory extends Factory
{
    public function definition(): array
    {
        return ['shop_id' => Shop::factory(), 'name' => fake()->unique()->words(3, true), 'description' => null];
    }
}
