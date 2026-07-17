<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class NotificationDestroyTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_user_can_delete_own_notification(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $user->notify($this->makeNotification('Delete me'));
        $notification = $user->notifications()->first();
        $this->assertSame(1, $user->notifications()->count());

        $this->actingAsUser($user);

        $this->deleteJson("/api/v1/notifications/{$notification->id}")
            ->assertNoContent();

        $this->assertSame(0, $user->fresh()->notifications()->count());
        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_user_cannot_delete_someone_elses_notification(): void
    {
        ['user' => $owner] = $this->createUserWithWorkspace();
        $other = User::factory()->create();
        $other->assignRole('user');
        $other->notify($this->makeNotification('Owned by other'));
        $otherNotification = $other->notifications()->first();

        $this->actingAsUser($owner);

        // The endpoint scopes the query to the current user's notifications,
        // so an alien id resolves to a 404 (ModelNotFoundException).
        $this->deleteJson("/api/v1/notifications/{$otherNotification->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('notifications', ['id' => $otherNotification->id]);
    }

    public function test_deleting_nonexistent_notification_returns_404(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->deleteJson('/api/v1/notifications/'.Str::uuid()->toString())
            ->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_delete_notification(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $user->notify($this->makeNotification('Anonymous'));
        $notification = $user->notifications()->first();

        $this->deleteJson("/api/v1/notifications/{$notification->id}")
            ->assertUnauthorized();

        $this->assertDatabaseHas('notifications', ['id' => $notification->id]);
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
