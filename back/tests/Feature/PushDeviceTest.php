<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class PushDeviceTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_upsert_device_returns_resource_envelope(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $installationId = Str::uuid()->toString();

        $this->actingAsUser($user);

        $response = $this->postJson('/api/v1/push/devices', [
            'installation_id' => $installationId,
            'fcm_token' => str_repeat('a', 64),
            'platform' => 'android',
            'notification_permission' => 'granted',
        ])
            ->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'installation_id',
                    'platform',
                    'notification_permission',
                    'last_seen_at',
                    'revoked_at',
                    'created_at',
                    'updated_at',
                ],
            ]);

        $this->assertSame($installationId, $response->json('data.installation_id'));
    }

    public function test_upsert_existing_device_returns_200(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $installationId = Str::uuid()->toString();

        $this->actingAsUser($user);

        $payload = [
            'installation_id' => $installationId,
            'fcm_token' => str_repeat('b', 64),
            'platform' => 'ios',
            'notification_permission' => 'granted',
        ];

        $this->postJson('/api/v1/push/devices', $payload)->assertCreated();

        $this->postJson('/api/v1/push/devices', array_merge($payload, [
            'fcm_token' => str_repeat('c', 64),
        ]))
            ->assertOk()
            ->assertJsonPath('data.installation_id', $installationId);
    }
}
