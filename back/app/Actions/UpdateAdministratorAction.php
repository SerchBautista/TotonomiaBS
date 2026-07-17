<?php

namespace App\Actions;

use App\Contracts\UpdateAdministratorActionInterface;
use App\Models\User;

class UpdateAdministratorAction implements UpdateAdministratorActionInterface
{
    public function execute(User $administrator, array $data): User
    {
        $administrator->name = $data['name'];
        $administrator->email = $data['email'];

        if (! empty($data['password'])) {
            $administrator->password = $data['password'];
        }

        $administrator->save();
        $administrator->syncRoles($data['roles']);
        $administrator->syncPermissions($data['permissions'] ?? []);
        $administrator->load(['roles:id,name', 'permissions:id,name']);

        return $administrator;
    }
}
