<?php

namespace Tests\Unit;

use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPushNotificationRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_notification_for_fcm_returns_only_granted_active_tokens(): void
    {
        $user = User::factory()->create();

        // Active with granted permission — should be included
        $activeGranted = PushDevice::factory()->create([
            'user_id' => $user->id,
            'fcm_token' => 'token-granted-active',
            'notification_permission' => 'granted',
            'revoked_at' => null,
        ]);

        // Active but denied permission — should be excluded
        PushDevice::factory()->create([
            'user_id' => $user->id,
            'fcm_token' => 'token-denied',
            'notification_permission' => 'denied',
            'revoked_at' => null,
        ]);

        // Active but not_determined — should be excluded
        PushDevice::factory()->create([
            'user_id' => $user->id,
            'fcm_token' => 'token-not-determined',
            'notification_permission' => 'not_determined',
            'revoked_at' => null,
        ]);

        // Granted but revoked — should be excluded
        PushDevice::factory()->create([
            'user_id' => $user->id,
            'fcm_token' => 'token-revoked',
            'notification_permission' => 'granted',
            'revoked_at' => now(),
        ]);

        $tokens = $user->routeNotificationForFcm();

        $this->assertCount(1, $tokens);
        $this->assertContains('token-granted-active', $tokens);
    }

    public function test_route_notification_for_fcm_returns_empty_array_when_no_active_granted_devices(): void
    {
        $user = User::factory()->create();

        PushDevice::factory()->create([
            'user_id' => $user->id,
            'fcm_token' => 'token-denied',
            'notification_permission' => 'denied',
            'revoked_at' => null,
        ]);

        $tokens = $user->routeNotificationForFcm();

        $this->assertIsArray($tokens);
        $this->assertEmpty($tokens);
    }
}
