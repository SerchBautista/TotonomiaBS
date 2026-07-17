<?php

namespace Tests\Unit\Actions;

use App\Actions\VerifyTwoFactorCodeAction;
use App\Exceptions\DomainRateLimitException;
use App\Exceptions\DomainValidationException;
use App\Models\TwoFactorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class VerifyTwoFactorCodeActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    private function createUserWithSession(string $code = '123456', array $overrides = []): array
    {
        $user = User::factory()->create();

        $session = TwoFactorSession::create(array_merge([
            'user_id' => $user->id,
            'token' => (string) Str::uuid(),
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ], $overrides));

        return ['user' => $user, 'session' => $session];
    }

    public function test_correct_code_returns_user_and_deletes_session(): void
    {
        ['user' => $user, 'session' => $session] = $this->createUserWithSession('654321');

        $action = new VerifyTwoFactorCodeAction;
        $result = $action->execute($session->token, '654321');

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($user->id, $result->id);
        $this->assertDatabaseCount('two_factor_sessions', 0);
    }

    public function test_wrong_code_throws_invalid_otp_code(): void
    {
        ['session' => $session] = $this->createUserWithSession('654321');

        $this->expectException(DomainValidationException::class);

        $action = new VerifyTwoFactorCodeAction;

        try {
            $action->execute($session->token, '000000');
        } catch (DomainValidationException $e) {
            $this->assertEquals('invalid_otp_code', $e->errorCode());

            throw $e;
        }
    }

    public function test_wrong_code_increments_attempts(): void
    {
        ['session' => $session] = $this->createUserWithSession('654321');

        $action = new VerifyTwoFactorCodeAction;

        try {
            $action->execute($session->token, '000000');
        } catch (DomainValidationException) {
            // expected
        }

        $session->refresh();
        $this->assertEquals(1, $session->attempts);
    }

    public function test_5_wrong_attempts_locks_session(): void
    {
        ['session' => $session] = $this->createUserWithSession('654321');

        $action = new VerifyTwoFactorCodeAction;

        for ($i = 0; $i < 5; $i++) {
            try {
                $action->execute($session->token, '000000');
            } catch (DomainValidationException) {
                // expected
            }
        }

        $session->refresh();
        $this->assertEquals(5, $session->attempts);
        $this->assertNotNull($session->locked_until);
        $this->assertTrue($session->locked_until->isFuture());
    }

    public function test_expired_session_throws_otp_code_expired(): void
    {
        ['session' => $session] = $this->createUserWithSession('654321', [
            'expires_at' => now()->subMinute(),
        ]);

        $this->expectException(DomainValidationException::class);

        $action = new VerifyTwoFactorCodeAction;

        try {
            $action->execute($session->token, '654321');
        } catch (DomainValidationException $e) {
            $this->assertEquals('otp_code_expired', $e->errorCode());

            throw $e;
        }
    }

    public function test_locked_session_throws_two_factor_locked(): void
    {
        ['session' => $session] = $this->createUserWithSession('654321', [
            'locked_until' => now()->addMinutes(15),
            'attempts' => 5,
        ]);

        $this->expectException(DomainRateLimitException::class);

        $action = new VerifyTwoFactorCodeAction;

        try {
            $action->execute($session->token, '654321');
        } catch (DomainRateLimitException $e) {
            $this->assertEquals('two_factor_locked', $e->errorCode());
            $this->assertArrayHasKey('retry_after', $e->meta());

            throw $e;
        }
    }

    public function test_invalid_session_token_throws_invalid_session(): void
    {
        $this->expectException(DomainValidationException::class);

        $action = new VerifyTwoFactorCodeAction;

        try {
            $action->execute('nonexistent-token', '123456');
        } catch (DomainValidationException $e) {
            $this->assertEquals('invalid_session', $e->errorCode());

            throw $e;
        }
    }
}
