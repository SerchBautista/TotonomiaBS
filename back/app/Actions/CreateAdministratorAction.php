<?php

namespace App\Actions;

use App\Contracts\CreateAdministratorActionInterface;
use App\Models\User;

class CreateAdministratorAction implements CreateAdministratorActionInterface
{
    public function execute(array $data): User
    {
        $administrator = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $administrator->syncRoles($data['roles']);
        $administrator->syncPermissions($data['permissions'] ?? []);
        $administrator->load(['roles:id,name', 'permissions:id,name']);

        return $administrator;
    }
}
