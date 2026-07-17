<?php

namespace App\Actions;

use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateWorkspaceCategoryAction
{
    /**
     * @param  array{name: string, icon?: string|null, color?: string|null, is_default?: bool|null}  $data
     */
    public function execute(User $owner, Workspace $workspace, array $data): Category
    {
        return DB::transaction(function () use ($owner, $workspace, $data): Category {
            $category = $owner->categories()->create($data);

            $workspace->enabledCategories()->syncWithoutDetaching([
                $category->id => [
                    'is_shared' => true,
                    'is_active' => true,
                ],
            ]);

            return $category;
        });
    }
}
