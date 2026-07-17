<?php

namespace Tests\Feature;

use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class PushDeviceRevokeTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_user_can_revoke_own_device_by_installation_id(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $device = PushDevice::factory()->create([
            'user_id' => $user->id,
            'revoked_at' => null,
        ]);
        $this->assertNull($device->revoked_at);

        $this->actingAsUser($user);

        $this->deleteJson("/api/v1/push/devices/{$device->installation_id}")
            ->assertNoContent();

        $this->assertNotNull($device->fresh()->revoked_at);
    }

    public function test_revoking_already_revoked_device_is_idempotent(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $device = PushDevice::factory()->create([
            'user_id' => $user->id,
            'revoked_at' => now(),
        ]);
        $firstRevokedAt = $device->revoked_at;

        $this->actingAsUser($user);

        $this->deleteJson("/api/v1/push/devices/{$device->installation_id}")
            ->assertNoContent();

        $secondRevokedAt = $device->fresh()->revoked_at;
        $this->assertNotNull($secondRevokedAt);
        $this->assertEquals(
            $firstRevokedAt->toIsoString(),
            $secondRevokedAt->toIsoString(),
        );
    }

    public function test_user_cannot_revoke_another_users_device(): void
    {
        ['user' => $owner] = $this->createUserWithWorkspace();
        $other = User::factory()->create();
        $other->assignRole('user');
        $otherDevice = PushDevice::factory()->create([
            'user_id' => $other->id,
            'revoked_at' => null,
        ]);

        $this->actingAsUser($owner);

        // The endpoint scopes the query to the current user's devices,
        // so an alien installation id resolves to a 404.
        $this->deleteJson("/api/v1/push/devices/{$otherDevice->installation_id}")
            ->assertNotFound();

        $this->assertNull($otherDevice->fresh()->revoked_at);
    }

    public function test_revoking_nonexistent_installation_id_returns_404(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->deleteJson('/api/v1/push/devices/'.Str::uuid()->toString())
            ->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_revoke_device(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $device = PushDevice::factory()->create([
            'user_id' => $user->id,
            'revoked_at' => null,
        ]);

        $this->deleteJson("/api/v1/push/devices/{$device->installation_id}")
            ->assertUnauthorized();

        $this->assertNull($device->fresh()->revoked_at);
    }
}
