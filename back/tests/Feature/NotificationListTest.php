<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Tests\CreatesAdmin;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class NotificationListTest extends TestCase
{
    use CreatesAdmin;
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_index_returns_flat_data_array_with_meta(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();

        $user->notify(new class extends Notification
        {
            public function via(object $notifiable): array
            {
                return ['database'];
            }

            /** @return array<string, string> */
            public function toArray(object $notifiable): array
            {
                return ['message' => 'Expense added'];
            }
        });

        $this->actingAsUser($user);

        $response = $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'data', 'read_at', 'created_at'],
                ],
                'meta' => ['current_page', 'last_page', 'total', 'unread_count'],
            ]);

        $this->assertIsArray($response->json('data'));
        $this->assertArrayNotHasKey('data', $response->json('data'));
        $response->assertJsonMissingPath('data.data');
        $this->assertSame('Expense added', $response->json('data.0.data.message'));
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/notifications')
            ->assertUnauthorized();
    }
}
