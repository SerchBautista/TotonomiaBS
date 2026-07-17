<?php

namespace App\Contracts;

use App\Models\Workspace;

interface AnalyticsSummaryActionInterface
{
    /**
     * @return array{
     *   total: string,
     *   period: array{from: string, to: string},
     *   by_category: list<array{id: string, name: string, icon: string|null, color: string|null, total: string, count: int}>,
     *   by_payment_method: list<array{id: string|null, name: string, type: string, total: string, count: int}>,
     * }
     */
    public function summary(Workspace $workspace, string $from, string $to): array;
}
