<?php

namespace App\Services\Auth;

use App\Contracts\AuthenticatorInterface;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AdminLoginService implements AuthenticatorInterface
{
    public function authenticate(array $credentials): ?User
    {
        if (! Auth::attempt($credentials)) {
            return null;
        }

        $user = Auth::user();
        if (! $user instanceof User || ! $user->hasRole('admin')) {
            Auth::logout();

            return null;
        }

        return $user;
    }
}
