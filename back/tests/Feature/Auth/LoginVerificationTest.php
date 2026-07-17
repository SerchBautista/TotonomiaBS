<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class LoginVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_unverified_email_returns_403(): void
    {
        $this->seed();

        $user = User::factory()->unverified()->create(['password' => bcrypt('StrongPass123')]);
        $user->assignRole('user');

        $response = $this->postJson('/api/v1/auth/user/login', [
            'email' => $user->email,
            'password' => 'StrongPass123',
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('status', 403)
            ->assertJsonPath('code', 'email_not_verified')
            ->assertJsonPath('message', 'Your email address is not verified. Please check your inbox.');
    }

    public function test_login_with_verified_email_returns_token(): void
    {
        $this->seed();

        $response = $this->postJson('/api/v1/auth/user/login', [
            'email' => 'user@example.com',
            'password' => 'StrongPass123',
        ]);

        $response->assertOk()->assertJsonStructure(['token']);
    }

    public function test_unverified_user_cannot_access_verified_routes_and_receives_specific_error_code(): void
    {
        $this->seed();

        $user = User::factory()->unverified()->create();
        $user->assignRole('user');

        Passport::actingAs($user, ['*'], 'api');

        $this->getJson('/api/v1/user/categories')
            ->assertStatus(403)
            ->assertJsonPath('status', 403)
            ->assertJsonPath('code', 'email_not_verified')
            ->assertJsonPath('message', 'Your email address is not verified. Please check your inbox.');
    }
}
