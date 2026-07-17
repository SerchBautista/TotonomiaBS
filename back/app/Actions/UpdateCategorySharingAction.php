<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\Workspace;

class UpdateCategorySharingAction
{
    public function __construct(
        private readonly CountCategoryUsageAction $countCategoryUsage,
    ) {}

    public function execute(Workspace $workspace, Category $category, bool $isLinked): void
    {
        if ($isLinked) {
            $workspace->enabledCategories()->syncWithoutDetaching([
                $category->id => ['is_shared' => true, 'is_active' => true],
            ]);

            return;
        }

        $usageCount = $this->countCategoryUsage->execute($category, $workspace);
        if ($usageCount > 0) {
            $workspace->enabledCategories()->syncWithoutDetaching([
                $category->id => ['is_shared' => false, 'is_active' => false],
            ]);

            return;
        }

        $workspace->enabledCategories()->detach($category->id);
    }
}
