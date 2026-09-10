<?php

namespace Database\Factories;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryMovement> */
class InventoryMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'product_id' => Product::factory(),
            'type' => 'opening_stock',
            'quantity' => 10,
            'reference_type' => null,
            'reference_id' => null,
            'reason' => 'Opening stock',
            'user_id' => User::factory(),
        ];
    }
}
