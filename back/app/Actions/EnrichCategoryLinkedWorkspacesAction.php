<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\Workspace;

class EnrichCategoryLinkedWorkspacesAction
{
    public function execute(Category $category, bool $forceReload = false): Category
    {
        if ($forceReload
            || ! $category->relationLoaded('workspaces')
            || ! array_key_exists('linked_workspaces_count', $category->getAttributes())) {
            $category->load([
                'workspaces' => fn ($query) => $query
                    ->where('category_workspace.is_shared', true)
                    ->select('workspaces.id', 'workspaces.name'),
            ]);
            $category->loadCount([
                'workspaces as linked_workspaces_count' => fn ($query) => $query->where('category_workspace.is_shared', true),
            ]);
        }

        $linkedWorkspaces = $category->workspaces
            ->map(fn (Workspace $workspace): array => ['id' => $workspace->id, 'name' => $workspace->name])
            ->values()
            ->all();

        $category->setAttribute('linked_workspaces', $linkedWorkspaces);

        return $category;
    }
}
