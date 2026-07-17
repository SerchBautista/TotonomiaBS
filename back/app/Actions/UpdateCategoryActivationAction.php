<?php

namespace App\Actions;

use App\Exceptions\DomainConflictException;
use App\Models\Category;
use App\Models\Workspace;

class UpdateCategoryActivationAction
{
    public function execute(Workspace $workspace, Category $category, bool $isActive): void
    {
        $isSharedInWorkspace = $workspace->enabledCategories()
            ->where('categories.id', $category->id)
            ->wherePivot('is_shared', true)
            ->exists();

        if (! $isSharedInWorkspace) {
            throw new DomainConflictException(
                'category_not_shared_in_workspace',
                __('api.errors.category_not_shared_in_workspace'),
            );
        }

        $workspace->enabledCategories()->updateExistingPivot($category->id, [
            'is_active' => $isActive,
        ]);
    }
}
