<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;

class GetValidWorkspaceCategoriesAction
{
    /**
     * @return Builder<Category>
     */
    public function execute(Workspace $workspace): Builder
    {
        return Category::query()
            ->where('user_id', $workspace->owner_id)
            ->whereHas('workspaces', function (Builder $builder) use ($workspace): void {
                $builder->where('workspaces.id', $workspace->id)
                    ->where('category_workspace.is_shared', true)
                    ->where('category_workspace.is_active', true);
            });
    }
}
