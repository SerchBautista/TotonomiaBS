<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\Workspace;
use App\ValueObjects\BulkOperationResult;

class BulkUpdateCategorySharingAction
{
    public function __construct(
        private readonly CountCategoryUsageAction $countCategoryUsage,
    ) {}

    public function execute(Workspace $workspace, string $operation): BulkOperationResult
    {
        $categories = Category::query()
            ->where('user_id', $workspace->owner_id)
            ->get();

        if ($operation === 'link_all') {
            foreach ($categories as $category) {
                $workspace->enabledCategories()->syncWithoutDetaching([
                    $category->id => ['is_shared' => true, 'is_active' => true],
                ]);
            }

            return new BulkOperationResult(
                operation: $operation,
                total: $categories->count(),
                processed: $categories->count(),
                blocked: 0,
                processedIds: $categories->pluck('id')->values()->all(),
                blockedIds: [],
            );
        }

        $processed = 0;
        $processedIds = [];
        $blockedIds = [];

        foreach ($categories as $category) {
            $isSharedInWorkspace = $workspace->enabledCategories()
                ->where('categories.id', $category->id)
                ->wherePivot('is_shared', true)
                ->exists();

            if (! $isSharedInWorkspace) {
                continue;
            }

            $usageCount = $this->countCategoryUsage->execute($category, $workspace);

            if ($usageCount > 0) {
                $workspace->enabledCategories()->updateExistingPivot($category->id, [
                    'is_shared' => false,
                    'is_active' => false,
                ]);
                $processed++;
                $processedIds[] = $category->id;

                continue;
            }

            $workspace->enabledCategories()->detach($category->id);
            $processed++;
            $processedIds[] = $category->id;
        }

        return new BulkOperationResult(
            operation: $operation,
            total: $categories->count(),
            processed: $processed,
            blocked: count($blockedIds),
            processedIds: $processedIds,
            blockedIds: $blockedIds,
        );
    }
}
