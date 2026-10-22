<?php

namespace Database\Factories;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title'    => fake()->sentence(3),
            'amount'   => fake()->randomFloat(2, 1, 500),
            'category' => fake()->randomElement(['food', 'transport', 'housing', 'leisure']),
            'date'     => fake()->dateTimeBetween('-3 months')->format('Y-m-d'),
        ];
    }
}
