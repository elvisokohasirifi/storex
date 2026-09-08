<?php

namespace Database\Factories;

use App\Models\LedgerEntry;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LedgerEntry> */
class LedgerEntryFactory extends Factory
{
    public function definition(): array
    {
        return ['shop_id' => Shop::factory(), 'created_by' => User::factory(), 'type' => 'expense', 'category' => 'Utilities', 'description' => 'Electricity', 'amount' => '45.00', 'occurred_on' => now()->toDateString()];
    }
}
