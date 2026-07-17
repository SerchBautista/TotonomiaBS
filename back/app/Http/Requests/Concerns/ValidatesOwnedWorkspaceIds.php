<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Str;

trait ValidatesOwnedWorkspaceIds
{
    protected function authorizeOwnedWorkspaceIds(): bool
    {
        $workspaceIds = $this->input('workspace_ids');

        if ($workspaceIds === null || ! is_array($workspaceIds)) {
            return true;
        }

        if ($workspaceIds === []) {
            return true;
        }

        foreach ($workspaceIds as $workspaceId) {
            if (! is_string($workspaceId) || ! Str::isUuid($workspaceId)) {
                return true;
            }
        }

        /** @var User|null $user */
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        $uniqueWorkspaceIds = array_values(array_unique($workspaceIds));

        $ownedCount = Workspace::query()
            ->whereIn('id', $uniqueWorkspaceIds)
            ->where('owner_id', $user->id)
            ->count();

        return $ownedCount === count($uniqueWorkspaceIds);
    }
}
