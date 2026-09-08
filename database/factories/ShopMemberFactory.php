<?php

namespace Database\Factories;

use App\Models\Shop;
use App\Models\ShopMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ShopMember> */
class ShopMemberFactory extends Factory
{
    public function definition(): array
    {
        return ['shop_id' => Shop::factory(), 'user_id' => User::factory(), 'role' => 'manager'];
    }
}
