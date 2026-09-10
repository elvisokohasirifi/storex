<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PurchaseOrder> */
class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        return ['shop_id' => Shop::factory(), 'supplier_id' => Supplier::factory(), 'reference' => 'PO-'.Str::upper(Str::random(8)), 'status' => 'draft', 'total_cost' => 0, 'notes' => null, 'created_by' => User::factory()];
    }
}
