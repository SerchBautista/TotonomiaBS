<?php

namespace App\Contracts;

use App\Models\User;

interface AuthenticatorInterface
{
    /**
     * Authenticate a user with the given credentials.
     *
     * Returns the authenticated User on success, or null on failure.
     */
    public function authenticate(#[\SensitiveParameter] array $credentials): ?User;
}
