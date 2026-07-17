<?php

namespace Tests\Unit\Actions;

use App\Actions\ResendTwoFactorCodeAction;
use App\Exceptions\DomainRateLimitException;
use App\Exceptions\DomainValidationException;
use App\Models\TwoFactorSession;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResendTwoFactorCodeActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    private function createUserWithSession(): array
    {
        $user = User::factory()->create(['two_factor_enabled' => true]);

        $session = TwoFactorSession::create([
            'user_id' => $user->id,
            'token' => (string) Str::uuid(),
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(config('two-factor.code_expiry_minutes', 5)),
        ]);

        return ['user' => $user, 'session' => $session];
    }

    public function test_resend_after_cooldown_returns_new_session(): void
    {
        ['user' => $user, 'session' => $session] = $this->createUserWithSession();

        // Move updated_at past cooldown
        DB::table('two_factor_sessions')
            ->where('id', $session->id)
            ->update(['updated_at' => now()->subSeconds(61)]);

        Notification::fake();

        $action = app(ResendTwoFactorCodeAction::class);
        $newSession = $action->execute($session->token);

        $this->assertInstanceOf(TwoFactorSession::class, $newSession);
        $this->assertNotEquals($session->token, $newSession->token);
        $this->assertEquals($user->id, $newSession->user_id);

        Notification::assertSentTo($user, TwoFactorCodeNotification::class);
    }

    public function test_resend_within_cooldown_throws_resend_cooldown(): void
    {
        ['session' => $session] = $this->createUserWithSession();

        // updated_at is now, so cooldown is not elapsed
        $this->expectException(DomainRateLimitException::class);

        $action = app(ResendTwoFactorCodeAction::class);

        try {
            $action->execute($session->token);
        } catch (DomainRateLimitException $e) {
            $this->assertEquals('resend_cooldown', $e->errorCode());
            $this->assertArrayHasKey('retry_after', $e->meta());

            throw $e;
        }
    }

    public function test_resend_with_invalid_token_throws_invalid_session(): void
    {
        $this->expectException(DomainValidationException::class);

        $action = app(ResendTwoFactorCodeAction::class);

        try {
            $action->execute('nonexistent-token');
        } catch (DomainValidationException $e) {
            $this->assertEquals('invalid_session', $e->errorCode());

            throw $e;
        }
    }

    public function test_resend_with_expired_session_throws_otp_code_expired(): void
    {
        ['session' => $session] = $this->createUserWithSession();

        DB::table('two_factor_sessions')
            ->where('id', $session->id)
            ->update([
                'expires_at' => now()->subMinute(),
                'updated_at' => now()->subMinutes(2),
            ]);

        $this->expectException(DomainValidationException::class);

        $action = app(ResendTwoFactorCodeAction::class);

        try {
            $action->execute($session->token);
        } catch (DomainValidationException $e) {
            $this->assertEquals('otp_code_expired', $e->errorCode());

            throw $e;
        }
    }

    public function test_resend_with_locked_session_throws_two_factor_locked(): void
    {
        ['session' => $session] = $this->createUserWithSession();

        $session->update([
            'locked_until' => now()->addMinutes(config('two-factor.lockout_minutes', 15)),
            'attempts' => config('two-factor.max_attempts', 5),
        ]);

        $this->expectException(DomainRateLimitException::class);

        $action = app(ResendTwoFactorCodeAction::class);

        try {
            $action->execute($session->token);
        } catch (DomainRateLimitException $e) {
            $this->assertEquals('two_factor_locked', $e->errorCode());
            $this->assertArrayHasKey('retry_after', $e->meta());

            throw $e;
        }
    }

    public function test_resend_deletes_previous_session(): void
    {
        ['session' => $session] = $this->createUserWithSession();

        DB::table('two_factor_sessions')
            ->where('id', $session->id)
            ->update(['updated_at' => now()->subSeconds(61)]);

        Notification::fake();

        $action = app(ResendTwoFactorCodeAction::class);
        $action->execute($session->token);

        // Old session should be deleted and a new one created
        $this->assertDatabaseCount('two_factor_sessions', 1);
        $this->assertDatabaseMissing('two_factor_sessions', ['id' => $session->id]);
    }
}
