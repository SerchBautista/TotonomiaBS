<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class NotificationMarkAsReadTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_user_can_mark_own_notification_as_read(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $user->notify($this->makeNotification('Mark me'));
        $notification = $user->notifications()->first();
        $this->assertNull($notification->read_at);

        $this->actingAsUser($user);

        $this->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonPath('message', 'OK');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_marking_notification_as_read_twice_is_idempotent(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $user->notify($this->makeNotification('Idempotent'));
        $notification = $user->notifications()->first();

        $this->actingAsUser($user);

        $this->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk();
        $firstReadAt = $notification->fresh()->read_at;
        $this->assertNotNull($firstReadAt);

        // Second call must succeed and preserve the original read_at timestamp.
        $this->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk();
        $secondReadAt = $notification->fresh()->read_at;
        $this->assertNotNull($secondReadAt);
        $this->assertEquals(
            $firstReadAt->toIsoString(),
            $secondReadAt->toIsoString(),
        );
    }

    public function test_user_cannot_mark_someone_elses_notification_as_read(): void
    {
        ['user' => $owner] = $this->createUserWithWorkspace();
        $other = User::factory()->create();
        $other->assignRole('user');
        $other->notify($this->makeNotification('Owned by other'));
        $otherNotification = $other->notifications()->first();

        $this->actingAsUser($owner);

        // The endpoint scopes the query to the current user's notifications,
        // so an alien id resolves to a 404 (ModelNotFoundException).
        $this->patchJson("/api/v1/notifications/{$otherNotification->id}/read")
            ->assertNotFound();

        $this->assertNull($otherNotification->fresh()->read_at);
    }

    public function test_marking_nonexistent_notification_returns_404(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->patchJson('/api/v1/notifications/'.Str::uuid()->toString().'/read')
            ->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_mark_notification_as_read(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $user->notify($this->makeNotification('Anonymous'));
        $notification = $user->notifications()->first();

        $this->patchJson("/api/v1/notifications/{$notification->id}/read")
            ->assertUnauthorized();

        $this->assertNull($notification->fresh()->read_at);
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
