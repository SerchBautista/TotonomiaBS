<?php

namespace App\Contracts;

use App\Models\User;
use App\Models\Workspace;

interface CreateWorkspaceActionInterface
{
    /**
     * Create a new workspace and add the owner as an admin member.
     *
     * @param  array{name: string, type: string, currency_code: string}  $data
     */
    public function execute(User $owner, array $data): Workspace;
}
