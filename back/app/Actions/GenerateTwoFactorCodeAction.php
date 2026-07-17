<?php

namespace App\Actions;

use App\Models\TwoFactorSession;
use App\Models\User;
use App\Notifications\TwoFactorCodeNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GenerateTwoFactorCodeAction
{
    public function execute(User $user): TwoFactorSession
    {
        // Delete previous sessions for this user
        $user->twoFactorSessions()->delete();

        // Generate 6-digit numeric code
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Create session with hashed code
        $session = TwoFactorSession::create([
            'user_id' => $user->id,
            'token' => (string) Str::uuid(),
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(config('two-factor.code_expiry_minutes', 5)),
        ]);

        // Send notification with plain text code
        $user->notify(new TwoFactorCodeNotification($code));

        return $session;
    }
}
