<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Code Expiry (minutes)
    |--------------------------------------------------------------------------
    |
    | How long a 2FA code remains valid before it expires.
    |
    */
    'code_expiry_minutes' => (int) env('TWO_FACTOR_CODE_EXPIRY_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Lockout Duration (minutes)
    |--------------------------------------------------------------------------
    |
    | How long a session is locked after exceeding max failed attempts.
    |
    */
    'lockout_minutes' => (int) env('TWO_FACTOR_LOCKOUT_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Resend Cooldown (seconds)
    |--------------------------------------------------------------------------
    |
    | Minimum seconds between resend requests for the same session.
    |
    */
    'resend_cooldown_seconds' => (int) env('TWO_FACTOR_RESEND_COOLDOWN_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Max Attempts
    |--------------------------------------------------------------------------
    |
    | Maximum failed verification attempts before the session is locked.
    |
    */
    'max_attempts' => (int) env('TWO_FACTOR_MAX_ATTEMPTS', 5),

    /*
    |--------------------------------------------------------------------------
    | Public Endpoint Rate Limits
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'login' => [
            'max_attempts' => (int) env('TWO_FACTOR_LOGIN_RATE_LIMIT', 10),
        ],
        'verify' => [
            'max_attempts' => (int) env('TWO_FACTOR_VERIFY_RATE_LIMIT', 20),
        ],
        'resend' => [
            'max_attempts' => (int) env('TWO_FACTOR_RESEND_RATE_LIMIT', 5),
        ],
    ],

];
