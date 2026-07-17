<?php

namespace App\Contracts;

use App\Models\Workspace;

interface AnalyticsHeatmapActionInterface
{
    /**
     * @return list<array{date: string, total: string, count: int}>
     */
    public function heatmap(Workspace $workspace, int $year, int $month): array;
}
