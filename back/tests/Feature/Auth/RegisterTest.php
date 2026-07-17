<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_user_can_register_with_valid_data(): void
    {
        $this->seed();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'newuser@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
        ]);

        $response->assertCreated()->assertJsonPath('message', fn ($msg) => str_contains($msg, 'Registration') || str_contains($msg, 'Registro'));

        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_registered_user_has_user_role(): void
    {
        $this->seed();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'newuser@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
        ]);

        $user = \App\Models\User::where('email', 'newuser@example.com')->first();
        $this->assertTrue($user->hasRole('user'));
    }

    public function test_registered_user_has_unverified_email(): void
    {
        $this->seed();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'newuser@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
        ]);

        $user = \App\Models\User::where('email', 'newuser@example.com')->first();
        $this->assertNull($user->email_verified_at);
    }

    public function test_register_with_duplicate_email_returns_422(): void
    {
        $this->seed();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Duplicate',
            'email' => 'user@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email'], 'fieldErrors');
    }

    public function test_register_with_mismatched_passwords_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'newuser@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['password'], 'fieldErrors');
    }

    public function test_register_with_password_missing_uppercase_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'newuser@example.com',
            'password' => 'weakpass123',
            'password_confirmation' => 'weakpass123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['password'], 'fieldErrors');
    }

    public function test_register_with_password_missing_number_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'newuser@example.com',
            'password' => 'WeakPassword',
            'password_confirmation' => 'WeakPassword',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['password'], 'fieldErrors');
    }

    public function test_register_with_missing_fields_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/register', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'password'], 'fieldErrors');
    }

    public function test_register_with_invalid_email_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test',
            'email' => 'not-an-email',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email'], 'fieldErrors');
    }
}
