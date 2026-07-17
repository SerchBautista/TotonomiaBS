<?php

namespace App\Contracts;

use App\Models\Workspace;

interface CheckBudgetThresholdActionInterface
{
    /**
     * Check whether the given expense pushes any budget past its alert threshold.
     *
     * @return list<array{
     *   scope: string,
     *   budget: string,
     *   spent: string,
     *   percentage: float,
     *   threshold: float,
     *   category_name?: string,
     * }>
     */
    public function execute(Workspace $workspace, string $categoryId, string $date): array;
}
