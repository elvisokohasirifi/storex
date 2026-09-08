<?php

namespace Database\Factories;

use App\Models\ModerationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ModerationLog> */
class ModerationLogFactory extends Factory
{
    public function definition(): array
    {
        return ['actor_id' => User::factory()->state(['is_platform_admin' => true]), 'subject_type' => 'shops', 'subject_id' => (string) Str::uuid(), 'status' => 'approved'];
    }
}
