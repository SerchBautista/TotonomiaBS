<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;
use App\Models\Workspace;

class BudgetPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user->id);
    }

    public function view(User $user, Budget $budget): bool
    {
        return $budget->workspace->hasMember($user->id);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        // The workspace owner must always be able to create budgets,
        // even if the workspace_user pivot is missing or has a stale role.
        if ($workspace->owner_id === $user->id) {
            return true;
        }

        $role = $workspace->memberRole($user->id);

        return in_array($role, ['owner', 'guest'], strict: true);
    }

    public function update(User $user, Budget $budget): bool
    {
        if ($budget->workspace->owner_id === $user->id) {
            return true;
        }

        $role = $budget->workspace->memberRole($user->id);

        return in_array($role, ['owner', 'guest'], strict: true);
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $this->update($user, $budget);
    }
}
