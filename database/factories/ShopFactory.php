<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Shop> */
class ShopFactory extends Factory
{
    public function definition(): array
    {
        return ['owner_id' => User::factory(), 'name' => fake()->company(), 'location' => 'Accra', 'contacts' => '+233241234567', 'email' => fake()->unique()->safeEmail(), 'status' => 'pending', 'currency' => 'GHS', 'enable_inventory_management' => false, 'momo_number' => null, 'momo_account_name' => null];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved']);
    }
}
