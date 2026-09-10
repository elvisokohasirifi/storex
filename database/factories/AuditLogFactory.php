<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AuditLog> */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return ['shop_id' => Shop::factory(), 'user_id' => User::factory(), 'action' => 'test.action', 'metadata' => []];
    }
}
