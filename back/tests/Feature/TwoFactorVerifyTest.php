<?php

namespace Tests\Feature;

use App\Models\TwoFactorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TwoFactorVerifyTest extends TestCase
{
    use RefreshDatabase;

    private function createSessionWithCode(User $user, string $plainCode = '123456'): TwoFactorSession
    {
        return TwoFactorSession::create([
            'user_id' => $user->id,
            'token' => (string) \Illuminate\Support\Str::uuid(),
            'code_hash' => Hash::make($plainCode),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    public function test_verify_otp_with_correct_code_returns_token(): void
    {
        $this->seed();

        $user = User::where('email', 'user@example.com')->first();
        $session = $this->createSessionWithCode($user, '654321');

        $response = $this->postJson('/api/v1/auth/user/verify-2fa', [
            'session_token' => $session->token,
            'code' => '654321',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'user']);
    }

    public function test_verify_otp_deletes_session_on_success(): void
    {
        $this->seed();

        $user = User::where('email', 'user@example.com')->first();
        $session = $this->createSessionWithCode($user, '654321');

        $this->postJson('/api/v1/auth/user/verify-2fa', [
            'session_token' => $session->token,
            'code' => '654321',
        ]);

        $this->assertDatabaseCount('two_factor_sessions', 0);
    }

    public function test_verify_otp_with_wrong_code_returns_error(): void
    {
        $this->seed();

        $user = User::where('email', 'user@example.com')->first();
        $session = $this->createSessionWithCode($user, '654321');

        $response = $this->postJson('/api/v1/auth/user/verify-2fa', [
            'session_token' => $session->token,
            'code' => '000000',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_otp_code');
    }

    public function test_verify_otp_after_5_attempts_locks_session(): void
    {
        $this->seed();

        $user = User::where('email', 'user@example.com')->first();
        $session = $this->createSessionWithCode($user, '654321');

        // Make 4 failed attempts (attempts will reach 4)
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/auth/user/verify-2fa', [
                'session_token' => $session->token,
                'code' => '000000',
            ]);
        }

        // 5th attempt — should lock the session
        $response = $this->postJson('/api/v1/auth/user/verify-2fa', [
            'session_token' => $session->token,
            'code' => '000000',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_otp_code');

        $session->refresh();
        $this->assertEquals(5, $session->attempts);
        $this->assertNotNull($session->locked_until);
    }

    public function test_verify_otp_with_expired_code_returns_error(): void
    {
        $this->seed();

        $user = User::where('email', 'user@example.com')->first();
        $session = $this->createSessionWithCode($user, '654321');
        $session->update(['expires_at' => now()->subMinute()]);

        $response = $this->postJson('/api/v1/auth/user/verify-2fa', [
            'session_token' => $session->token,
            'code' => '654321',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('code', 'otp_code_expired');
    }

    public function test_verify_otp_with_invalid_session_token_returns_error(): void
    {
        $response = $this->postJson('/api/v1/auth/user/verify-2fa', [
            'session_token' => 'nonexistent-token',
            'code' => '123456',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_session');
    }

    public function test_verify_otp_with_locked_session_returns_retry_after(): void
    {
        $this->seed();

        $user = User::where('email', 'user@example.com')->first();
        $session = $this->createSessionWithCode($user, '654321');
        $session->update([
            'locked_until' => now()->addMinutes(15),
            'attempts' => 5,
        ]);

        $response = $this->postJson('/api/v1/auth/user/verify-2fa', [
            'session_token' => $session->token,
            'code' => '654321',
        ]);

        $response
            ->assertStatus(429)
            ->assertJsonPath('code', 'two_factor_locked')
            ->assertJsonStructure(['status', 'code', 'message', 'meta' => ['retry_after']]);
    }

    public function test_verify_otp_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/user/verify-2fa', []);

        $response
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error');
    }

    public function test_verify_otp_validates_code_format(): void
    {
        $response = $this->postJson('/api/v1/auth/user/verify-2fa', [
            'session_token' => 'some-token',
            'code' => '12a456',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error');
    }

    public function test_verify_is_rate_limited(): void
    {
        $this->seed();

        $user = User::where('email', 'user@example.com')->first();
        $session = $this->createSessionWithCode($user, '654321');

        for ($attempt = 0; $attempt < config('two-factor.rate_limits.verify.max_attempts'); $attempt++) {
            DB::table('two_factor_sessions')
                ->where('id', $session->id)
                ->update([
                    'attempts' => 0,
                    'locked_until' => null,
                    'updated_at' => now(),
                ]);

            $this->postJson('/api/v1/auth/user/verify-2fa', [
                'session_token' => $session->token,
                'code' => '000000',
            ]);
        }

        $response = $this->postJson('/api/v1/auth/user/verify-2fa', [
            'session_token' => $session->token,
            'code' => '000000',
        ]);

        $response
            ->assertStatus(429)
            ->assertJsonPath('code', 'too_many_requests');
    }
}
