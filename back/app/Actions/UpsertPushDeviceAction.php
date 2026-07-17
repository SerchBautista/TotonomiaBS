<?php

namespace App\Actions;

use App\Models\PushDevice;
use App\Models\User;

class UpsertPushDeviceAction
{
    /**
     * Upsert a push device for the given user.
     * If a device with the same user_id + installation_id already exists,
     * it updates the token and metadata. Otherwise, it creates a new device.
     *
     * @param  array{installation_id: string, fcm_token: string, platform: string, notification_permission?: string}  $data
     */
    public function execute(User $user, array $data): PushDevice
    {
        $data['notification_permission'] ??= 'not_determined';

        $device = PushDevice::where('user_id', $user->id)
            ->where('installation_id', $data['installation_id'])
            ->first();

        if ($device) {
            // Reactivate if previously revoked
            $device->update([
                'fcm_token' => $data['fcm_token'],
                'platform' => $data['platform'],
                'notification_permission' => $data['notification_permission'],
                'last_seen_at' => now(),
                'token_refreshed_at' => now(),
                'revoked_at' => null,
            ]);
        } else {
            $device = PushDevice::create([
                'user_id' => $user->id,
                'installation_id' => $data['installation_id'],
                'fcm_token' => $data['fcm_token'],
                'platform' => $data['platform'],
                'notification_permission' => $data['notification_permission'],
                'last_seen_at' => now(),
                'token_refreshed_at' => now(),
            ]);
        }

        return $device;
    }
}
