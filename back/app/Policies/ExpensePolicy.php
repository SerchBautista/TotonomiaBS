<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use App\Models\Workspace;

class ExpensePolicy
{
    /**
     * Determine whether the user can view any expenses in a workspace.
     * The user must be a member of the workspace.
     */
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user->id);
    }

    /**
     * Determine whether the user can view an expense.
     */
    public function view(User $user, Expense $expense): bool
    {
        return $expense->workspace->hasMember($user->id);
    }

    /**
     * Determine whether the user can create an expense in a workspace.
     * Owners and guests can create; non-members cannot.
     * Non-owners are blocked in free owner workspaces.
     */
    public function create(User $user, Workspace $workspace): bool
    {
        if ($workspace->owner_id === $user->id) {
            return true;
        }

        $role = $workspace->memberRole($user->id);

        if (! in_array($role, ['owner', 'guest'], strict: true)) {
            return false;
        }

        if (! $workspace->ownerHasPremium()) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can update an expense.
     * Only the creator, guests, or workspace owner can update.
     * Non-owners are blocked in free owner workspaces.
     */
    public function update(User $user, Expense $expense): bool
    {
        // Workspace owner can always update regardless of plan
        if ($expense->workspace->owner_id === $user->id) {
            return true;
        }

        // Non-owners are blocked in free owner workspaces
        if (! $expense->workspace->ownerHasPremium()) {
            return false;
        }

        if ($expense->user_id === $user->id) {
            return true;
        }

        $role = $expense->workspace->memberRole($user->id);

        return $role === 'guest';
    }

    /**
     * Determine whether the user can delete an expense.
     * Same rules as update.
     */
    public function delete(User $user, Expense $expense): bool
    {
        return $this->update($user, $expense);
    }
}
