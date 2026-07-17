<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_with_valid_token_updates_password(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('StrongPass123', $user->fresh()->password));
    }

    public function test_reset_password_with_invalid_token_returns_422(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'password_reset_invalid_token')
            ->assertJsonPath('message', 'This password reset token is invalid or has expired.');
    }

    public function test_reset_password_with_mismatched_passwords_returns_422(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'StrongPass123',
            'password_confirmation' => 'DifferentPass123',
        ]);

        $response->assertUnprocessable();
    }

    public function test_reset_password_token_can_only_be_used_once(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $payload = [
            'token' => $token,
            'email' => $user->email,
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
        ];

        $this->postJson('/api/v1/auth/password/reset', $payload)->assertOk();
        $this->postJson('/api/v1/auth/password/reset', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'password_reset_invalid_token');
    }

    public function test_reset_password_with_password_too_short_returns_422(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/password/reset', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertUnprocessable();
    }
}
