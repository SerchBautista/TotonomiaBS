<?php

namespace App\Policies;

use App\Models\BudgetAdjustment;
use App\Models\User;
use App\Models\Workspace;

class BudgetAdjustmentPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user->id);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        if ($workspace->owner_id === $user->id) {
            return true;
        }

        $role = $workspace->memberRole($user->id);

        return in_array($role, ['owner', 'guest'], strict: true);
    }

    public function delete(User $user, BudgetAdjustment $adjustment): bool
    {
        if ($adjustment->workspace->owner_id === $user->id) {
            return true;
        }

        $role = $adjustment->workspace->memberRole($user->id);

        return in_array($role, ['owner', 'guest'], strict: true);
    }
}
