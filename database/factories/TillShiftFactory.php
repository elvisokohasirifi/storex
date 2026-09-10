<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\TillShift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TillShift> */
class TillShiftFactory extends Factory
{
    public function definition(): array
    {
        return ['shop_id' => Shop::factory(), 'user_id' => User::factory(), 'status' => 'open', 'opening_cash' => 0, 'expected_cash' => 0, 'actual_cash' => null, 'opened_at' => now()];
    }
}
