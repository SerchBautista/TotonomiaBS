<?php

namespace App\Actions;

use App\Models\User;

class SetDefaultWorkspaceAction
{
    public function execute(User $user, string $workspaceId): User
    {
        $user->update(['default_workspace_id' => $workspaceId]);

        return $user->fresh();
    }
}
