<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return ['shop_id' => Shop::factory(), 'name' => 'Customer', 'phone' => fake()->unique()->phoneNumber(), 'email' => fake()->safeEmail(), 'address' => null];
    }
}
