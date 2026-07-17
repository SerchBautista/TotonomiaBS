<?php

namespace Database\Factories;

use App\Models\OtherPaymentMethod;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OtherPaymentMethod>
 */
class OtherPaymentMethodFactory extends Factory
{
    protected $model = OtherPaymentMethod::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['PayPal', 'Transferencia', 'Cheque', 'Criptomoneda']),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
