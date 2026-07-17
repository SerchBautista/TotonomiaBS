<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'payment_type' => 'card',
            'payment_instrument_id' => Card::factory(),
            'fixed_expense_id' => null,
            'amount' => fake()->randomFloat(2, 1, 5000),
            'date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
