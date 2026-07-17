<?php

namespace App\Contracts;

use App\Models\Workspace;

interface AnalyticsProjectionActionInterface
{
    /**
     * @return array{
     *   current_month_total: string,
     *   days_elapsed: int,
     *   days_in_month: int,
     *   daily_average: string,
     *   projected_total: string,
     * }
     */
    public function projection(Workspace $workspace): array;
}
