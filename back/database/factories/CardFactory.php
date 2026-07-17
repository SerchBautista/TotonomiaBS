<?php

namespace Database\Factories;

use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Card>
 */
class CardFactory extends Factory
{
    protected $model = Card::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Visa Personal', 'Mastercard', 'Amex Gold']),
            'card_type' => fake()->randomElement(['credit', 'debit']),
            'brand' => fake()->randomElement(['visa', 'mastercard', 'amex', null]),
            'last_4_digits' => (string) fake()->numberBetween(1000, 9999),
        ];
    }

    public function credit(): static
    {
        return $this->state(['card_type' => 'credit']);
    }

    public function debit(): static
    {
        return $this->state(['card_type' => 'debit']);
    }
}
