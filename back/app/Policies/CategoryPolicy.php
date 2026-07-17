<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;

class CategoryPolicy
{
    /**
     * Any workspace member can view categories.
     * Owner sees all theirs; members see only enabled ones (filtered in controller).
     */
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user->id);
    }

    /**
     * Owners can always create categories.
     * Guests can if the workspace owner has premium AND they have the explicit permission.
     */
    public function create(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }

    /**
     * Only the category owner can update it.
     */
    public function update(User $user, Category $category): bool
    {
        return $user->id === $category->user_id;
    }

    /**
     * Only the category owner can delete it.
     */
    public function delete(User $user, Category $category): bool
    {
        return $user->id === $category->user_id;
    }

    /**
     * Only the workspace owner can assign/unassign categories to a workspace.
     */
    public function assign(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }
}
