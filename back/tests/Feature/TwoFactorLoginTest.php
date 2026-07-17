<?php

namespace Tests\Feature;

use App\Models\TwoFactorSession;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class TwoFactorLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_with_2fa_disabled_returns_token_directly(): void
    {
        $this->seed();

        $response = $this->postJson('/api/v1/auth/user/login', [
            'email' => 'user@example.com',
            'password' => 'StrongPass123',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'user']);
    }

    public function test_login_with_2fa_enabled_returns_two_factor_required(): void
    {
        $this->seed();

        $user = User::where('email', 'user@example.com')->first();
        $user->update(['two_factor_enabled' => true]);

        Notification::fake();

        $response = $this->postJson('/api/v1/auth/user/login', [
            'email' => 'user@example.com',
            'password' => 'StrongPass123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('two_factor_required', true)
            ->assertJsonStructure(['two_factor_required', 'session_token', 'message']);
    }

    public function test_login_with_2fa_does_not_emit_passport_token(): void
    {
        $this->seed();

        $user = User::where('email', 'user@example.com')->first();
        $user->update(['two_factor_enabled' => true]);

        Notification::fake();

        $response = $this->postJson('/api/v1/auth/user/login', [
            'email' => 'user@example.com',
            'password' => 'StrongPass123',
        ]);

        $response->assertOk();
        $this->assertArrayNotHasKey('token', $response->json());
        $this->assertArrayNotHasKey('token_type', $response->json());
    }

    public function test_login_with_2fa_sends_email_notification(): void
    {
        $this->seed();

        $user = User::where('email', 'user@example.com')->first();
        $user->update(['two_factor_enabled' => true]);

        Notification::fake();

        $this->postJson('/api/v1/auth/user/login', [
            'email' => 'user@example.com',
            'password' => 'StrongPass123',
        ]);

        Notification::assertSentTo($user, TwoFactorCodeNotification::class);
    }

    public function test_login_with_2fa_creates_two_factor_session(): void
    {
        $this->seed();

        $user = User::where('email', 'user@example.com')->first();
        $user->update(['two_factor_enabled' => true]);

        Notification::fake();

        $this->postJson('/api/v1/auth/user/login', [
            'email' => 'user@example.com',
            'password' => 'StrongPass123',
        ]);

        $this->assertDatabaseCount('two_factor_sessions', 1);
        $this->assertDatabaseHas('two_factor_sessions', [
            'user_id' => $user->id,
        ]);
    }

    public function test_login_with_2fa_and_unverified_email_returns_403(): void
    {
        $this->seed();

        $user = User::where('email', 'user@example.com')->first();
        $user->two_factor_enabled = true;
        $user->email_verified_at = null;
        $user->save();

        $response = $this->postJson('/api/v1/auth/user/login', [
            'email' => 'user@example.com',
            'password' => 'StrongPass123',
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('code', 'email_not_verified');
    }

    public function test_login_with_locked_two_factor_session_returns_429_and_does_not_create_new_session(): void
    {
        $this->seed();

        $user = User::where('email', 'user@example.com')->first();
        $user->update(['two_factor_enabled' => true]);

        TwoFactorSession::create([
            'user_id' => $user->id,
            'token' => (string) Str::uuid(),
            'code_hash' => Hash::make('123456'),
            'attempts' => config('two-factor.max_attempts'),
            'locked_until' => now()->addMinutes(config('two-factor.lockout_minutes')),
            'expires_at' => now()->addMinutes(config('two-factor.code_expiry_minutes')),
        ]);

        Notification::fake();

        $response = $this->postJson('/api/v1/auth/user/login', [
            'email' => 'user@example.com',
            'password' => 'StrongPass123',
        ]);

        $response
            ->assertStatus(429)
            ->assertJsonPath('code', 'two_factor_locked')
            ->assertJsonStructure(['status', 'code', 'message', 'meta' => ['retry_after']]);

        $this->assertDatabaseCount('two_factor_sessions', 1);
        Notification::assertNothingSent();
    }

    public function test_login_is_rate_limited(): void
    {
        $this->seed();

        for ($attempt = 0; $attempt < config('two-factor.rate_limits.login.max_attempts'); $attempt++) {
            $this->postJson('/api/v1/auth/user/login', [
                'email' => 'user@example.com',
                'password' => 'WrongPass123',
            ]);
        }

        $response = $this->postJson('/api/v1/auth/user/login', [
            'email' => 'user@example.com',
            'password' => 'WrongPass123',
        ]);

        $response
            ->assertStatus(429)
            ->assertJsonPath('code', 'too_many_requests');
    }
}
