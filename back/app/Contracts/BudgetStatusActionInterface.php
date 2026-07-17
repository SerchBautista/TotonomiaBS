<?php

namespace App\Contracts;

use App\Models\Workspace;
use Carbon\Carbon;

interface BudgetStatusActionInterface
{
    /**
     * @return array{
     *   month: string,
     *   general: array<string, mixed>|null,
     *   categories: list<array<string, mixed>>,
     * }
     */
    public function execute(Workspace $workspace, ?Carbon $month): array;
}
