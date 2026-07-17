<?php

namespace App\Actions;

use App\Models\User;

class UpdateUserPreferencesAction
{
    /**
     * @param  array{theme: string, locale: string, timezone: string}  $preferences
     */
    public function execute(User $user, array $preferences): User
    {
        $user->update([
            'theme' => $preferences['theme'],
            'locale' => $preferences['locale'],
            'timezone' => $preferences['timezone'],
        ]);

        return $user->fresh();
    }
}
