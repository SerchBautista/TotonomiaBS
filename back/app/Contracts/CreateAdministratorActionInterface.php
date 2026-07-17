<?php

namespace App\Contracts;

use App\Models\User;

interface CreateAdministratorActionInterface
{
    public function execute(array $data): User;
}
