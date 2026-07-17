<?php

namespace App\Policies;

use App\Models\FixedExpense;
use App\Models\User;
use App\Models\Workspace;

class FixedExpensePolicy
{
    /**
     * Any workspace member can view fixed expenses.
     */
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user->id);
    }

    /**
     * Any workspace member can view a fixed expense.
     */
    public function view(User $user, FixedExpense $fixedExpense): bool
    {
        return $fixedExpense->workspace->hasMember($user->id);
    }

    /**
     * Owners can always create fixed expenses.
     * Guests can if the workspace owner has premium AND they have the explicit permission.
     */
    public function create(User $user, Workspace $workspace): bool
    {
        // Workspace owner can always create fixed expenses,
        // even if the workspace_user pivot is missing or has a stale role.
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

        $pivot = $workspace->members()->where('user_id', $user->id)->first()?->pivot;

        return (bool) ($pivot?->can_add_fixed_expenses ?? false);
    }

    /**
     * The creator, guests, or workspace owner can update fixed expenses.
     * Non-owners are blocked if workspace owner has a free plan.
     */
    public function update(User $user, FixedExpense $fixedExpense): bool
    {
        if ($fixedExpense->workspace->owner_id === $user->id) {
            return true;
        }

        if (! $fixedExpense->workspace->ownerHasPremium()) {
            return false;
        }

        if ($fixedExpense->user_id === $user->id) {
            return true;
        }

        return $fixedExpense->workspace->memberRole($user->id) === 'guest';
    }

    /**
     * Same rules as update.
     */
    public function delete(User $user, FixedExpense $fixedExpense): bool
    {
        return $this->update($user, $fixedExpense);
    }
}
