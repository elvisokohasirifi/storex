<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Shop;
use App\Models\StockBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockBatch> */
class StockBatchFactory extends Factory
{
    public function definition(): array
    {
        return ['shop_id' => Shop::factory(), 'product_id' => Product::factory(), 'batch_number' => fake()->bothify('BATCH-###'), 'expiry_date' => now()->addMonth()->toDateString(), 'quantity_received' => 10, 'quantity_remaining' => 10, 'unit_cost' => '8.00'];
    }
}
