<?php

namespace App\Contracts;

use Carbon\Carbon;

interface MarkOverdueOccurrencesActionInterface
{
    /**
     * Mark all pending occurrences with due_date < $asOf as overdue.
     *
     * @return int Number of occurrences marked as overdue
     */
    public function execute(?Carbon $asOf = null): int;
}
