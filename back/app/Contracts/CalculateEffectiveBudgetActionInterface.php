<?php

namespace App\Contracts;

use App\Models\Workspace;
use Carbon\Carbon;

interface CalculateEffectiveBudgetActionInterface
{
    /**
     * Calculate the effective budget for a category in a given month,
     * taking into account base budget and all adjustments.
     *
     * @return array{
     *     base_amount: float,
     *     adjustments_in: float,
     *     adjustments_out: float,
     *     effective_budget: float,
     * }
     */
    public function execute(Workspace $workspace, string $categoryId, Carbon $month): array;
}
