<?php

namespace App\Actions;

use App\Exceptions\DomainRateLimitException;
use App\Exceptions\DomainValidationException;
use App\Models\TwoFactorSession;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;

class VerifyTwoFactorCodeAction
{
    public function execute(#[SensitiveParameter] string $sessionToken, #[SensitiveParameter] string $code): User
    {
        $session = TwoFactorSession::where('token', $sessionToken)->first();

        if ($session === null) {
            throw new DomainValidationException(
                'invalid_session',
                __('api.auth.invalid_session'),
            );
        }

        // Check lockout
        if ($session->isLockedOut()) {
            $retryAfter = (int) now()->diffInSeconds($session->locked_until, false);

            throw new DomainRateLimitException(
                'two_factor_locked',
                __('api.auth.two_factor_locked'),
                ['retry_after' => $retryAfter],
            );
        }

        // Check expiration
        if ($session->isExpired()) {
            throw new DomainValidationException(
                'otp_code_expired',
                __('api.auth.otp_code_expired'),
            );
        }

        // Verify code
        if (! Hash::check($code, $session->code_hash)) {
            $session->increment('attempts');

            if ($session->attempts >= config('two-factor.max_attempts', 5)) {
                $session->update(['locked_until' => now()->addMinutes(config('two-factor.lockout_minutes', 15))]);
            }

            throw new DomainValidationException(
                'invalid_otp_code',
                __('api.auth.invalid_otp_code'),
            );
        }

        // Code is correct — delete session and return user
        $user = $session->user;
        $session->delete();

        return $user;
    }
}
