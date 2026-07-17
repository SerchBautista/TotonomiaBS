<?php

namespace App\Policies;

use App\Models\OtherPaymentMethod;
use App\Models\User;
use App\Models\Workspace;

class OtherPaymentMethodPolicy
{
    /**
     * Any workspace member can view other payment methods.
     */
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user->id);
    }

    /**
     * Any workspace member can view an other payment method.
     */
    public function view(User $user, OtherPaymentMethod $otherPaymentMethod): bool
    {
        return $otherPaymentMethod->workspace->hasMember($user->id);
    }

    /**
     * Only guests and workspace owner can create other payment methods.
     * Non-owners are blocked if workspace owner has a free plan.
     */
    public function create(User $user, Workspace $workspace): bool
    {
        if ($workspace->owner_id === $user->id) {
            return true;
        }

        if (! $workspace->ownerHasPremium()) {
            return false;
        }

        return $workspace->memberRole($user->id) === 'guest';
    }

    /**
     * Only guests and workspace owner can update other payment methods.
     */
    public function update(User $user, OtherPaymentMethod $otherPaymentMethod): bool
    {
        if ($otherPaymentMethod->workspace->owner_id === $user->id) {
            return true;
        }

        if (! $otherPaymentMethod->workspace->ownerHasPremium()) {
            return false;
        }

        return $otherPaymentMethod->workspace->memberRole($user->id) === 'guest';
    }

    /**
     * Same rules as update.
     */
    public function delete(User $user, OtherPaymentMethod $otherPaymentMethod): bool
    {
        return $this->update($user, $otherPaymentMethod);
    }
}
