<?php

namespace App\Contracts;

use App\Models\User;

interface CreateDefaultWorkspaceActionInterface
{
    public function execute(User $user): void;
}
