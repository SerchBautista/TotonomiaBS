<?php

namespace App\Actions;

use App\Contracts\MarkOverdueOccurrencesActionInterface;
use App\Models\FixedExpenseOccurrence;
use Carbon\Carbon;

class MarkOverdueOccurrencesAction implements MarkOverdueOccurrencesActionInterface
{
    /**
     * Mark all pending occurrences with due_date < $asOf as overdue.
     *
     * @return int Number of occurrences marked as overdue
     */
    public function execute(?Carbon $asOf = null): int
    {
        $asOf ??= Carbon::today();

        return FixedExpenseOccurrence::where('status', 'pending')
            ->where('due_date', '<', $asOf->toDateString())
            ->update(['status' => 'overdue']);
    }
}
