<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PurchaseOrderItem> */
class PurchaseOrderItemFactory extends Factory
{
    public function definition(): array
    {
        return ['purchase_order_id' => PurchaseOrder::factory(), 'product_id' => Product::factory(), 'quantity' => 5, 'unit_cost' => '8.00'];
    }
}
