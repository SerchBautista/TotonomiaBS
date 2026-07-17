<?php

namespace Tests\Unit\Actions;

use App\Actions\RevokePushDeviceAction;
use App\Models\PushDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevokePushDeviceActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_revoke_sets_revoked_at_timestamp(): void
    {
        $user = User::factory()->create();
        $device = PushDevice::factory()->create([
            'user_id' => $user->id,
            'revoked_at' => null,
        ]);

        $action = new RevokePushDeviceAction;
        $action->execute($device);

        $this->assertNotNull($device->fresh()->revoked_at);
    }

    public function test_revoke_is_idempotent(): void
    {
        $user = User::factory()->create();
        $device = PushDevice::factory()->create([
            'user_id' => $user->id,
            'revoked_at' => null,
        ]);

        $action = new RevokePushDeviceAction;
        $action->execute($device);
        $firstRevokedAt = $device->fresh()->revoked_at;

        // Second revoke call
        $action->execute($device->fresh());
        $secondRevokedAt = $device->fresh()->revoked_at;

        $this->assertNotNull($firstRevokedAt);
        $this->assertEquals($firstRevokedAt->toIsoString(), $secondRevokedAt->toIsoString());
    }
}
