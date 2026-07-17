<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Collection;

class ListMyCategoriesAction
{
    public function __construct(
        private readonly EnrichCategoryLinkedWorkspacesAction $enrichCategoryLinkedWorkspaces,
    ) {}

    /**
     * @return Collection<int, Category>
     */
    public function execute(User $user): Collection
    {
        return Category::query()
            ->where('user_id', $user->id)
            ->with([
                'workspaces' => fn ($query) => $query
                    ->where('category_workspace.is_shared', true)
                    ->select('workspaces.id', 'workspaces.name'),
            ])
            ->withCount([
                'workspaces as linked_workspaces_count' => fn ($query) => $query->where('category_workspace.is_shared', true),
            ])
            ->orderBy('name')
            ->get()
            ->each(fn (Category $category): Category => $this->enrichCategoryLinkedWorkspaces->execute($category));
    }
}
