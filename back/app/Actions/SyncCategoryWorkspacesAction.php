<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class SyncCategoryWorkspacesAction
{
    public function __construct(
        private readonly CountCategoryUsageAction $countCategoryUsage,
    ) {}

    /**
     * Synchronize the workspaces linked to a personal category.
     *
     * Workspaces being removed where the category is still in use are kept as
     * read-only (is_shared=false, is_active=false) to preserve historical data.
     * If the category already has pivots to workspaces the acting user does not
     * own, the sync is rejected and no pivots are mutated.
     *
     * @param  array<int, string>  $workspaceIds
     */
    public function execute(Category $category, User $user, array $workspaceIds): void
    {
        $workspaceIds = array_values(array_unique($workspaceIds));

        $this->ensureOwnership($workspaceIds, $user);

        DB::transaction(function () use ($category, $user, $workspaceIds): void {
            $currentIds = $category->workspaces()->pluck('workspaces.id')->all();
            $currentIds = array_map('strval', $currentIds);

            $this->ensureOwnership($currentIds, $user);

            $toRemove = array_diff($currentIds, $workspaceIds);

            if ($workspaceIds !== []) {
                $category->workspaces()->syncWithoutDetaching(
                    array_fill_keys($workspaceIds, ['is_shared' => true, 'is_active' => true])
                );
            }

            foreach ($toRemove as $workspaceId) {
                $workspace = Workspace::find($workspaceId);

                if ($workspace === null) {
                    continue;
                }

                $usageCount = $this->countCategoryUsage->execute($category, $workspace);

                if ($usageCount > 0) {
                    $category->workspaces()->syncWithoutDetaching([
                        $workspaceId => ['is_shared' => false, 'is_active' => false],
                    ]);
                } else {
                    $category->workspaces()->detach($workspaceId);
                }
            }
        });
    }

    /**
     * @param  array<int, string>  $workspaceIds
     */
    private function ensureOwnership(array $workspaceIds, User $user): void
    {
        $ownedCount = Workspace::query()
            ->whereIn('id', $workspaceIds)
            ->where('owner_id', $user->id)
            ->count();

        if ($ownedCount !== count($workspaceIds)) {
            throw new AuthorizationException;
        }
    }
}
