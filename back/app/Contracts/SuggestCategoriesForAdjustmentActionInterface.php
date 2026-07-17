<?php

namespace App\Contracts;

use App\Models\Workspace;
use Carbon\Carbon;

interface SuggestCategoriesForAdjustmentActionInterface
{
    /**
     * Suggest categories that have available funds for a budget adjustment.
     *
     * @return array<int, array{
     *     category_id: string,
     *     category_name: string|null,
     *     category_icon: string|null,
     *     category_color: string|null,
     *     base_budget: string,
     *     effective_budget: string,
     *     spent: string,
     *     available: string,
     * }>
     */
    public function execute(Workspace $workspace, string $excludeCategoryId, Carbon $month): array;
}
