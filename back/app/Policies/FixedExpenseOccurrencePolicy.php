<?php

namespace App\Policies;

use App\Models\FixedExpenseOccurrence;
use App\Models\User;

class FixedExpenseOccurrencePolicy
{
    /**
     * Any workspace member can view occurrences of a fixed expense they have access to.
     */
    public function view(User $user, FixedExpenseOccurrence $occurrence): bool
    {
        return $occurrence->fixedExpense->workspace->hasMember($user->id);
    }

    /**
     * Guests, owners, and the fixed expense creator can mark occurrences as paid.
     * Non-owners are blocked if workspace owner has a free plan.
     */
    public function pay(User $user, FixedExpenseOccurrence $occurrence): bool
    {
        $fixedExpense = $occurrence->fixedExpense;
        $workspace = $fixedExpense->workspace;

        if ($workspace->owner_id === $user->id) {
            return true;
        }

        if (! $workspace->ownerHasPremium()) {
            return false;
        }

        if ($fixedExpense->user_id === $user->id) {
            return true;
        }

        return in_array($workspace->memberRole($user->id), ['owner', 'guest'], strict: true);
    }

    /**
     * Same rules as pay for updating occurrence details.
     */
    public function update(User $user, FixedExpenseOccurrence $occurrence): bool
    {
        return $this->pay($user, $occurrence);
    }
}
