<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\Workspace;
use Illuminate\Support\Collection;

class ListWorkspaceCategorySharingAction
{
    public function __construct(
        private readonly CountCategoryUsageAction $countCategoryUsage,
    ) {}

    /**
     * @return Collection<int, Category>
     */
    public function execute(Workspace $workspace): Collection
    {
        return Category::query()
            ->where('user_id', $workspace->owner_id)
            ->with(['workspaces' => fn ($query) => $query->where('workspaces.id', $workspace->id)->select('workspaces.id')])
            ->orderBy('name')
            ->get()
            ->each(function (Category $category) use ($workspace): void {
                $workspaceLink = $category->workspaces->first();
                $isLinked = (bool) ($workspaceLink?->pivot?->is_shared ?? false);
                $isActive = $isLinked ? (bool) ($workspaceLink?->pivot?->is_active ?? true) : false;
                $usageCount = $this->countCategoryUsage->execute($category, $workspace);
                $state = $this->resolveState($workspaceLink !== null, $isLinked, $isActive);

                $category->setAttribute('is_linked', $isLinked);
                $category->setAttribute('is_active_in_workspace', $isActive);
                $category->setAttribute('is_in_use_in_workspace', $usageCount > 0);
                $category->setAttribute('is_valid_for_transactions', $isLinked && $isActive);
                $category->setAttribute('state', $state);
            });
    }

    private function resolveState(bool $hasPivot, bool $isLinked, bool $isActive): string
    {
        if ($isLinked && $isActive) {
            return 'linked';
        }

        if ($hasPivot) {
            return 'read_only_linked';
        }

        return 'not_linked';
    }
}
