<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class NotificationUnreadCountTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_authenticated_user_with_mixed_read_and_unread_notifications_returns_correct_count(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();

        // Create 5 notifications, then mark 2 of them as read.
        // Expected unread_count = 3.
        for ($i = 0; $i < 5; $i++) {
            $user->notify($this->makeNotification("Notification {$i}"));
        }

        $notifIds = $user->notifications()->pluck('id')->all();
        $this->assertCount(5, $notifIds);

        // Mark the last 2 as read.
        $user->notifications()->whereIn('id', array_slice($notifIds, -2))->get()->each->markAsRead();

        $this->actingAsUser($user);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('count', 3);
    }

    public function test_authenticated_user_with_no_notifications_returns_zero_count(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();

        $this->actingAsUser($user);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('count', 0);
    }

    public function test_unauthenticated_user_cannot_access_unread_count(): void
    {
        $this->getJson('/api/v1/notifications/unread-count')
            ->assertUnauthorized();
    }

    public function test_unread_count_only_counts_notifications_of_current_user(): void
    {
        ['user' => $owner] = $this->createUserWithWorkspace();
        $other = User::factory()->create();
        $other->assignRole('user');

        // 4 unread for the current user
        for ($i = 0; $i < 4; $i++) {
            $owner->notify($this->makeNotification("Owner unread {$i}"));
        }
        // 2 unread for the other user
        for ($i = 0; $i < 2; $i++) {
            $other->notify($this->makeNotification("Other unread {$i}"));
        }

        $this->actingAsUser($owner);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertOk()
            ->assertJsonPath('count', 4);
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
