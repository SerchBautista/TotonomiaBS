<?php

namespace App\Actions;

use App\Exceptions\DomainConflictException;
use App\Models\Category;

class DeleteCategoryAction
{
    public function __construct(
        private readonly CountCategoryUsageAction $countCategoryUsage,
    ) {}

    public function execute(Category $category): void
    {
        if ($this->countCategoryUsage->execute($category) > 0) {
            throw new DomainConflictException(
                'category_in_use',
                __('api.errors.category_in_use'),
            );
        }

        $category->delete();
    }
}
