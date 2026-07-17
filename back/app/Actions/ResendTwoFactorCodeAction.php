<?php

namespace App\Actions;

use App\Exceptions\DomainRateLimitException;
use App\Exceptions\DomainValidationException;
use App\Models\TwoFactorSession;
use SensitiveParameter;

class ResendTwoFactorCodeAction
{
    public function __construct(
        private readonly GenerateTwoFactorCodeAction $generateAction,
    ) {}

    public function execute(#[SensitiveParameter] string $sessionToken): TwoFactorSession
    {
        $session = TwoFactorSession::where('token', $sessionToken)->first();

        if ($session === null) {
            throw new DomainValidationException(
                'invalid_session',
                __('api.auth.invalid_session'),
            );
        }

        if ($session->isExpired()) {
            throw new DomainValidationException(
                'otp_code_expired',
                __('api.auth.otp_code_expired'),
            );
        }

        // Check lockout (H2 fix — prevent bypass via resend)
        if ($session->isLockedOut()) {
            $retryAfter = (int) now()->diffInSeconds($session->locked_until, false);

            throw new DomainRateLimitException(
                'two_factor_locked',
                __('api.auth.two_factor_locked'),
                ['retry_after' => $retryAfter],
            );
        }

        // Check resend cooldown
        $cooldownSeconds = config('two-factor.resend_cooldown_seconds', 60);
        $secondsSinceUpdate = (int) $session->updated_at->diffInSeconds(now());

        if ($secondsSinceUpdate < $cooldownSeconds) {
            $retryAfter = $cooldownSeconds - $secondsSinceUpdate;

            throw new DomainRateLimitException(
                'resend_cooldown',
                __('api.auth.resend_cooldown'),
                ['retry_after' => $retryAfter],
            );
        }

        // Regenerate code for the same user
        return $this->generateAction->execute($session->user);
    }
}
