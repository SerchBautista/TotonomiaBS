<?php

namespace App\Actions;

use App\Exceptions\DomainValidationException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;

class ToggleTwoFactorAction
{
    public function execute(User $user, bool $enabled, #[SensitiveParameter] string $password): User
    {
        if (! Hash::check($password, $user->password)) {
            throw new DomainValidationException(
                'invalid_password',
                __('api.auth.invalid_password'),
                fieldErrors: ['password' => [__('api.auth.invalid_password')]],
            );
        }

        $user->update(['two_factor_enabled' => $enabled]);

        if (! $enabled) {
            $user->twoFactorSessions()->delete();
        }

        return $user->fresh();
    }
}
