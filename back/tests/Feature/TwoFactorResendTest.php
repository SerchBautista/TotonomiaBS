<?php

namespace Tests\Feature;

use App\Models\TwoFactorSession;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class TwoFactorResendTest extends TestCase
{
    use RefreshDatabase;

    private function createSession(User $user): TwoFactorSession
    {
        return TwoFactorSession::create([
            'user_id' => $user->id,
            'token' => (string) Str::uuid(),
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    public function test_resend_code_after_cooldown_succeeds(): void
    {
        $this->seed();

        $user = User::where('email', 'user@example.com')->first();
        $session = $this->createSession($user);

        // Use DB query to bypass Eloquent's auto-timestamp management
        DB::table('two_factor_sessions')
            ->where('id', $session->id)
            ->update(['updated_at' => now()->subSeconds(61)]);

        Notification::fake();

        $response = $this->postJson('/api/v1/auth/user/resend-2fa', [
            'session_token' => $session->token,
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['message', 'session_token']);

        Notification::assertSentTo($user, TwoFactorCodeNotification::class);
    }

    public function test_resend_code_within_cooldown_returns_429(): void
    {
        $this->seed();

        $user = User::where('email', 'user@example.com')->first();
        $session = $this->createSession($user);

        // updated_at is now, so cooldown is not elapsed
        $response = $this->postJson('/api/v1/auth/user/resend-2fa', [
            'session_token' => $session->token,
        ]);

        $response
            ->assertStatus(429)
            ->assertJsonPath('code', 'resend_cooldown')
            ->assertJsonStructure(['status', 'code', 'message', 'meta' => ['retry_after']]);
    }

    public function test_resend_with_invalid_session_returns_error(): void
    {
        $response = $this->postJson('/api/v1/auth/user/resend-2fa', [
            'session_token' => 'nonexistent-token',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_session');
    }

    public function test_resend_with_expired_session_returns_error(): void
    {
        $this->seed();

        $user = User::where('email', 'user@example.com')->first();
        $session = $this->createSession($user);

        // Use DB query to bypass Eloquent's auto-timestamp management
        DB::table('two_factor_sessions')
            ->where('id', $session->id)
            ->update([
                'expires_at' => now()->subMinute(),
                'updated_at' => now()->subMinutes(2),
            ]);

        $response = $this->postJson('/api/v1/auth/user/resend-2fa', [
            'session_token' => $session->token,
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('code', 'otp_code_expired');
    }

    public function test_resend_validates_required_session_token(): void
    {
        $response = $this->postJson('/api/v1/auth/user/resend-2fa', []);

        $response
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error');
    }

    public function test_resend_with_locked_session_returns_429(): void
    {
        $this->seed();

        $user = User::where('email', 'user@example.com')->first();
        $session = $this->createSession($user);
        $session->update([
            'locked_until' => now()->addMinutes(config('two-factor.lockout_minutes')),
            'attempts' => config('two-factor.max_attempts'),
        ]);

        $response = $this->postJson('/api/v1/auth/user/resend-2fa', [
            'session_token' => $session->token,
        ]);

        $response
            ->assertStatus(429)
            ->assertJsonPath('code', 'two_factor_locked')
            ->assertJsonStructure(['status', 'code', 'message', 'meta' => ['retry_after']]);
    }

    public function test_resend_is_rate_limited(): void
    {
        $this->seed();

        $user = User::where('email', 'user@example.com')->first();
        $session = $this->createSession($user);

        for ($attempt = 0; $attempt < config('two-factor.rate_limits.resend.max_attempts'); $attempt++) {
            DB::table('two_factor_sessions')
                ->where('id', $session->id)
                ->update(['updated_at' => now()->subSeconds(config('two-factor.resend_cooldown_seconds'))]);

            $response = $this->postJson('/api/v1/auth/user/resend-2fa', [
                'session_token' => $session->fresh()->token,
            ]);

            $session = TwoFactorSession::where('token', $response->json('session_token'))->first();
        }

        $response = $this->postJson('/api/v1/auth/user/resend-2fa', [
            'session_token' => $session->token,
        ]);

        $response
            ->assertStatus(429)
            ->assertJsonPath('code', 'too_many_requests');
    }
}
