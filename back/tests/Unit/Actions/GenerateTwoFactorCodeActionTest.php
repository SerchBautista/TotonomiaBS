<?php

namespace Tests\Unit\Actions;

use App\Actions\GenerateTwoFactorCodeAction;
use App\Models\TwoFactorSession;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GenerateTwoFactorCodeActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    private function createUser(): User
    {
        return User::factory()->create();
    }

    public function test_generates_session_with_correct_structure(): void
    {
        $user = $this->createUser();
        Notification::fake();

        $action = new GenerateTwoFactorCodeAction;
        $session = $action->execute($user);

        $this->assertInstanceOf(TwoFactorSession::class, $session);
        $this->assertEquals($user->id, $session->user_id);
        $this->assertNotEmpty($session->token);
        $this->assertNotEmpty($session->code_hash);
        $this->assertEquals(0, $session->attempts);
        $this->assertNotNull($session->expires_at);
    }

    public function test_session_expires_in_5_minutes(): void
    {
        $user = $this->createUser();
        Notification::fake();

        $action = new GenerateTwoFactorCodeAction;
        $session = $action->execute($user);

        $this->assertTrue(
            $session->expires_at->between(
                now()->addMinutes(4)->addSeconds(50),
                now()->addMinutes(5)->addSeconds(10),
            )
        );
    }

    public function test_deletes_previous_sessions(): void
    {
        $user = $this->createUser();
        Notification::fake();

        $action = new GenerateTwoFactorCodeAction;

        // Create first session
        $action->execute($user);
        $this->assertDatabaseCount('two_factor_sessions', 1);

        // Create second session — should delete the first
        $action->execute($user);
        $this->assertDatabaseCount('two_factor_sessions', 1);
    }

    public function test_sends_notification_to_user(): void
    {
        $user = $this->createUser();
        Notification::fake();

        $action = new GenerateTwoFactorCodeAction;
        $action->execute($user);

        Notification::assertSentTo($user, TwoFactorCodeNotification::class);
    }

    public function test_code_hash_is_not_plain_text(): void
    {
        $user = $this->createUser();
        Notification::fake();

        $action = new GenerateTwoFactorCodeAction;
        $session = $action->execute($user);

        // The hash should not be a 6-digit number
        $this->assertGreaterThan(6, strlen($session->code_hash));
    }
}
