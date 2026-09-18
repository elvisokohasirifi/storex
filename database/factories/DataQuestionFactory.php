<?php

namespace Database\Factories;

use App\Models\DataQuestion;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DataQuestion> */
class DataQuestionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'user_id' => User::factory(),
            'question' => 'What were total sales this year?',
            'answer' => [
                'type' => 'metric',
                'title' => 'Total sales for this year',
                'summary' => 'Sales were GHS 0.00 from 0 paid orders.',
                'rows' => [],
            ],
        ];
    }
}
