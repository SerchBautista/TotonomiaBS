<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class NotificationMarkAllAsReadTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_user_with_unread_notifications_can_mark_all_as_read(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        for ($i = 0; $i < 4; $i++) {
            $user->notify($this->makeNotification("Unread {$i}"));
        }
        $this->assertSame(4, $user->unreadNotifications()->count());

        $this->actingAsUser($user);

        $this->patchJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('message', 'OK');

        $this->assertSame(0, $user->fresh()->unreadNotifications()->count());
        $this->assertSame(4, $user->notifications()->count());
        $this->assertNotNull($user->notifications()->first()->read_at);
    }

    public function test_user_with_no_notifications_marking_all_is_a_noop(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->assertSame(0, $user->notifications()->count());

        $this->actingAsUser($user);

        $this->patchJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('message', 'OK');

        $this->assertSame(0, $user->fresh()->notifications()->count());
    }

    public function test_mark_all_as_read_only_affects_current_user_notifications(): void
    {
        ['user' => $owner] = $this->createUserWithWorkspace();
        $other = User::factory()->create();
        $other->assignRole('user');

        for ($i = 0; $i < 3; $i++) {
            $owner->notify($this->makeNotification("Owner {$i}"));
        }
        for ($i = 0; $i < 2; $i++) {
            $other->notify($this->makeNotification("Other {$i}"));
        }

        $this->actingAsUser($owner);

        $this->patchJson('/api/v1/notifications/read-all')
            ->assertOk();

        // All 3 of the owner are read, the other user still has 2 unread.
        $this->assertSame(0, $owner->fresh()->unreadNotifications()->count());
        $this->assertSame(2, $other->fresh()->unreadNotifications()->count());
    }

    public function test_unauthenticated_user_cannot_mark_all_as_read(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $user->notify($this->makeNotification('Anonymous'));
        $this->assertSame(1, $user->unreadNotifications()->count());

        $this->patchJson('/api/v1/notifications/read-all')
            ->assertUnauthorized();

        $this->assertSame(1, $user->fresh()->unreadNotifications()->count());
    }

    private function makeNotification(string $message): Notification
    {
        return new class($message) extends Notification
        {
            public function __construct(private readonly string $payload) {}

            public function via(object $notifiable): array
            {
                return ['database'];
            }

            /** @return array<string, string> */
            public function toArray(object $notifiable): array
            {
                return ['message' => $this->payload];
            }
        };
    }
}
