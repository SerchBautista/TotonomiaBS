<?php

namespace App\Actions;

use App\Contracts\CreateOccurrenceActionInterface;
use App\Contracts\ProcessFixedExpensesActionInterface;
use App\Models\FixedExpense;
use App\Notifications\FixedExpenseEmailNotification;
use App\Notifications\FixedExpenseProcessedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ProcessFixedExpensesAction implements ProcessFixedExpensesActionInterface
{
    public function __construct(
        private readonly CreateOccurrenceActionInterface $createOccurrenceAction,
    ) {}

    /**
     * Process all active fixed expenses that are due today or overdue.
     * Creates a pending FixedExpenseOccurrence for each due expense.
     * Idempotent: each expense is only processed once per due date.
     *
     * @return array{processed: int, skipped: int, failed: int}
     */
    public function execute(?Carbon $asOf = null): array
    {
        $asOf ??= Carbon::today();

        $dueExpenses = FixedExpense::with(['user', 'workspace.members'])
            ->where('is_active', true)
            ->where(function ($query) use ($asOf) {
                $query->where(function ($q) use ($asOf) {
                    // Has alert_date: trigger when alert_date is reached
                    $q->whereNotNull('alert_date')
                        ->where('alert_date', '<=', $asOf);
                })->orWhere(function ($q) use ($asOf) {
                    // No alert_date: use the original due-date behavior
                    $q->whereNull('alert_date')
                        ->where('next_due_date', '<=', $asOf);
                });
            })
            ->get();

        $processed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($dueExpenses as $fixed) {
            try {
                $occurrence = $this->createOccurrenceAction->execute($fixed);

                if ($occurrence->wasRecentlyCreated) {
                    $fixed->user->notify(new FixedExpenseProcessedNotification($fixed, $occurrence));
                    $fixed->user->notify(new FixedExpenseEmailNotification($fixed, $occurrence));

                    foreach ($fixed->workspace->members as $member) {
                        if ($member->id !== $fixed->user_id) {
                            $member->notify(new FixedExpenseProcessedNotification($fixed, $occurrence));
                            $member->notify(new FixedExpenseEmailNotification($fixed, $occurrence));
                        }
                    }

                    $fixed->advanceNextDueDate();
                    $processed++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                Log::error('ProcessFixedExpensesAction failed', [
                    'fixed_expense_id' => $fixed->id,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        return compact('processed', 'skipped', 'failed');
    }
}
