<?php

namespace App\Actions;

use App\Models\PushDevice;

class RevokePushDeviceAction
{
    /**
     * Revoke a push device, preventing further FCM delivery to it.
     */
    public function execute(PushDevice $pushDevice): void
    {
        $pushDevice->revoke();
    }
}
