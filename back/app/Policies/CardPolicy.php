<?php

namespace App\Policies;

use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;

class CardPolicy
{
    /**
     * Any workspace member can view cards.
     */
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user->id);
    }

    /**
     * Any workspace member can view a card.
     */
    public function view(User $user, Card $card): bool
    {
        return $card->workspace->hasMember($user->id);
    }

    /**
     * Only guests and workspace owner can create cards.
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
     * Only guests and workspace owner can update cards.
     */
    public function update(User $user, Card $card): bool
    {
        if ($card->workspace->owner_id === $user->id) {
            return true;
        }

        if (! $card->workspace->ownerHasPremium()) {
            return false;
        }

        return $card->workspace->memberRole($user->id) === 'guest';
    }

    /**
     * Same rules as update.
     */
    public function delete(User $user, Card $card): bool
    {
        return $this->update($user, $card);
    }
}
