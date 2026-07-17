<?php

namespace Database\Factories;

use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PushDevice>
 */
class PushDeviceFactory extends Factory
{
    protected $model = PushDevice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'installation_id' => fake()->uuid(),
            'fcm_token' => fake()->sha256(),
            'platform' => fake()->randomElement(['ios', 'android', 'web']),
            'notification_permission' => 'granted',
            'last_seen_at' => now(),
            'token_refreshed_at' => now(),
        ];
    }

    public function android(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => 'android',
        ]);
    }

    public function ios(): static
    {
        return $this->state(fn (array $attributes) => [
            'platform' => 'ios',
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'revoked_at' => now(),
        ]);
    }
}
