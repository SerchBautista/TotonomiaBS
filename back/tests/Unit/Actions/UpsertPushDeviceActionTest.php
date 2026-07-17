<?php

namespace Tests\Unit\Actions;

use App\Actions\UpsertPushDeviceAction;
use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpsertPushDeviceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_new_device_when_none_exists(): void
    {
        $user = User::factory()->create();
        $action = new UpsertPushDeviceAction;

        $device = $action->execute($user, [
            'installation_id' => 'inst-new-001',
            'fcm_token' => 'fcm-token-aaa',
            'platform' => 'ios',
            'notification_permission' => 'granted',
        ]);

        $this->assertTrue($device->wasRecentlyCreated);
        $this->assertEquals('inst-new-001', $device->installation_id);
        $this->assertEquals('fcm-token-aaa', $device->fcm_token);
        $this->assertEquals('ios', $device->platform);
        $this->assertNull($device->revoked_at);
        $this->assertDatabaseCount('push_devices', 1);
    }

    public function test_updates_existing_device_for_same_user_and_installation(): void
    {
        $user = User::factory()->create();
        PushDevice::factory()->create([
            'user_id' => $user->id,
            'installation_id' => 'inst-001',
            'fcm_token' => 'old-token',
            'platform' => 'android',
        ]);

        $action = new UpsertPushDeviceAction;

        $device = $action->execute($user, [
            'installation_id' => 'inst-001',
            'fcm_token' => 'new-token',
            'platform' => 'web',
            'notification_permission' => 'denied',
        ]);

        $this->assertFalse($device->wasRecentlyCreated);
        $this->assertEquals('new-token', $device->fcm_token);
        $this->assertEquals('web', $device->platform);
        $this->assertEquals('denied', $device->notification_permission);
        $this->assertDatabaseCount('push_devices', 1);
    }

    public function test_reactivates_revoked_device_on_upsert(): void
    {
        $user = User::factory()->create();
        PushDevice::factory()->create([
            'user_id' => $user->id,
            'installation_id' => 'inst-001',
            'revoked_at' => now()->subDay(),
        ]);

        $action = new UpsertPushDeviceAction;

        $device = $action->execute($user, [
            'installation_id' => 'inst-001',
            'fcm_token' => 'renewed-token',
            'platform' => 'android',
            'notification_permission' => 'granted',
        ]);

        $this->assertNull($device->revoked_at);
        $this->assertEquals('renewed-token', $device->fcm_token);
    }

    public function test_different_users_can_have_same_installation_id(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $action = new UpsertPushDeviceAction;

        $deviceA = $action->execute($userA, [
            'installation_id' => 'inst-shared',
            'fcm_token' => 'token-a',
            'platform' => 'android',
            'notification_permission' => 'granted',
        ]);

        $deviceB = $action->execute($userB, [
            'installation_id' => 'inst-shared',
            'fcm_token' => 'token-b',
            'platform' => 'ios',
            'notification_permission' => 'granted',
        ]);

        $this->assertDatabaseCount('push_devices', 2);
        $this->assertNotEquals($deviceA->id, $deviceB->id);
        $this->assertEquals('inst-shared', $deviceA->installation_id);
        $this->assertEquals('inst-shared', $deviceB->installation_id);
    }

    public function test_defaults_notification_permission_when_omitted(): void
    {
        $user = User::factory()->create();
        $action = new UpsertPushDeviceAction;

        $device = $action->execute($user, [
            'installation_id' => 'inst-default-perm',
            'fcm_token' => 'token-default',
            'platform' => 'android',
        ]);

        $this->assertEquals('not_determined', $device->notification_permission);
    }
}
