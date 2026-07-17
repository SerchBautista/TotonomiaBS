<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Budget>
 */
class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'category_id' => null,
            'amount' => fake()->randomFloat(2, 100, 5000),
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
            'alert_threshold' => '0.80',
            'alert_enabled' => true,
        ];
    }

    public function forCategory(string|Category|null $category = null): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category instanceof Category ? $category->id : ($category ?? Category::factory()),
        ]);
    }

    public function effectiveFrom(string $yearMonth): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_from' => Carbon::parse($yearMonth)->startOfMonth()->toDateString(),
        ]);
    }
}
