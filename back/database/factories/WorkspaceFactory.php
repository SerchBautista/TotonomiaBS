<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'owner_id' => User::factory(),
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(['personal', 'familiar', 'empresa']),
            'currency_code' => fake()->randomElement(['USD', 'MXN', 'EUR']),
        ];
    }

    public function personal(): static
    {
        return $this->state(['type' => 'personal']);
    }
}
