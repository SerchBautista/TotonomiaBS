<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\Category;
use App\Models\FixedExpense;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FixedExpense>
 */
class FixedExpenseFactory extends Factory
{
    protected $model = FixedExpense::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'payment_type' => 'card',
            'payment_instrument_id' => Card::factory(),
            'amount' => fake()->randomFloat(2, 50, 2000),
            'description' => fake()->optional()->sentence(),
            'frequency' => fake()->randomElement(['daily', 'weekly', 'monthly', 'yearly']),
            'next_due_date' => now()->toDateString(),
            'is_active' => true,
        ];
    }

    public function dueToday(): static
    {
        return $this->state(['next_due_date' => now()->toDateString(), 'is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
