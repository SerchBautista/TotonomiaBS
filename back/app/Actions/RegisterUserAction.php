<?php

namespace App\Actions;

use App\Contracts\RegisterUserActionInterface;
use App\Events\UserRegistered;
use App\Models\User;

class RegisterUserAction implements RegisterUserActionInterface
{
    /**
     * @param  array<string, string>  $data
     */
    public function execute(array $data): User
    {
        /** @var User $user */
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->assignRole('user');

        UserRegistered::dispatch($user);

        $user->sendEmailVerificationNotification();

        return $user;
    }
}
