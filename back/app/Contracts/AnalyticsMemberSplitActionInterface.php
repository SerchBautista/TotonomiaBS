<?php

namespace App\Contracts;

use App\Models\Workspace;

interface AnalyticsMemberSplitActionInterface
{
    /**
     * @return array{
     *   month: string,
     *   total: string,
     *   member_count: int,
     *   fair_share: string,
     *   members: list<array{id: string, name: string, paid: string, balance: string}>,
     *   settlements: list<array{from_id: string, from_name: string, to_id: string, to_name: string, amount: string}>,
     * }
     */
    public function memberSplit(Workspace $workspace, int $year, int $month): array;
}
