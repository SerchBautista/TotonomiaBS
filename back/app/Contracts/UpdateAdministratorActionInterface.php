<?php

namespace App\Contracts;

use App\Models\User;

interface UpdateAdministratorActionInterface
{
    public function execute(User $administrator, array $data): User;
}
