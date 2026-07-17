<?php

namespace App\Services\Auth;

use App\Contracts\AuthenticatorInterface;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserLoginService implements AuthenticatorInterface
{
    public function authenticate(#[\SensitiveParameter] array $credentials): ?User
    {
        if (! Auth::attempt($credentials)) {
            return null;
        }

        $user = Auth::user();

        // Acepta user regular O admin (admin ⊇ user por jerarquía).
        // AdminLoginService sigue siendo exclusivo; la jerarquía es unidireccional.
        if (! $user instanceof User || (! $user->hasRole('user') && ! $user->hasRole('admin'))) {
            Auth::logout();

            return null;
        }

        return $user;
    }
}
