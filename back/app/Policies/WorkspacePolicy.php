<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    /**
     * Determine whether the user can view any workspaces.
     * Users can only list their own workspaces.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view a workspace.
     * The user must be a member (any role).
     */
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user->id);
    }

    /**
     * Determine whether the user can create workspaces.
     * Free users are limited to 1 owned workspace.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('premium')) {
            return true;
        }

        return $user->ownedWorkspaces()->count() < 1;
    }

    /**
     * Determine whether the user can update a workspace.
     * Only the owner can update workspace settings.
     */
    public function update(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }

    /**
     * Determine whether the user can delete a workspace.
     * Only the owner can delete.
     */
    public function delete(User $user, Workspace $workspace): bool
    {
        return $workspace->owner_id === $user->id;
    }

    /**
     * Determine whether the user can manage workspace members.
     * Only the owner can manage members, and the workspace owner must be premium.
     */
    public function manageMembers(User $user, Workspace $workspace): bool
    {
        return $workspace->ownerHasPremium() && $workspace->owner_id === $user->id;
    }
}
