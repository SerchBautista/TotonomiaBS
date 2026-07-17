<?php

namespace Database\Factories;

use App\Models\FixedExpense;
use App\Models\FixedExpenseOccurrence;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FixedExpenseOccurrence>
 */
class FixedExpenseOccurrenceFactory extends Factory
{
    protected $model = FixedExpenseOccurrence::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'fixed_expense_id' => FixedExpense::factory(),
            'due_date' => now()->toDateString(),
            'suggested_amount' => fake()->randomFloat(2, 50, 2000),
            'status' => 'pending',
        ];
    }

    public function overdue(): static
    {
        return $this->state([
            'status' => 'overdue',
            'due_date' => now()->subDays(5)->toDateString(),
        ]);
    }

    public function paid(): static
    {
        return $this->state([
            'status' => 'paid',
            'actual_amount' => fake()->randomFloat(2, 50, 2000),
            'paid_at' => now(),
        ]);
    }
}
