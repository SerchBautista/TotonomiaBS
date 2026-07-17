<?php

namespace App\Contracts;

use Carbon\Carbon;

interface ProcessFixedExpensesActionInterface
{
    /**
     * Process all active fixed expenses that are due today or overdue.
     * Idempotent: each expense is only processed once per due date.
     *
     * @return array{processed: int, skipped: int, failed: int}
     */
    public function execute(?Carbon $asOf = null): array;
}
