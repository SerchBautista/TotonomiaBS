<?php

namespace App\Actions;

use App\Contracts\CreateOccurrenceActionInterface;
use App\Jobs\SendPaymentReminderJob;
use App\Models\FixedExpense;
use App\Models\FixedExpenseOccurrence;
use Carbon\Carbon;

class CreateOccurrenceAction implements CreateOccurrenceActionInterface
{
    /**
     * Create a pending occurrence for a fixed expense.
     * Idempotent: returns existing occurrence if one already exists for the same due date.
     * Dispatches a payment reminder job 2 days before due date if reminders are enabled.
     */
    public function execute(FixedExpense $fixedExpense): FixedExpenseOccurrence
    {
        $occurrence = FixedExpenseOccurrence::firstOrCreate(
            [
                'fixed_expense_id' => $fixedExpense->id,
                'due_date' => $fixedExpense->next_due_date,
            ],
            [
                'suggested_amount' => $fixedExpense->amount,
                'payment_type' => $fixedExpense->payment_type,
                'payment_instrument_id' => $fixedExpense->payment_instrument_id,
                'status' => 'pending',
            ]
        );

        if ($occurrence->wasRecentlyCreated && $fixedExpense->reminders_enabled) {
            $reminderAt = Carbon::parse($fixedExpense->next_due_date)->subDays(2);

            if ($reminderAt->isFuture()) {
                SendPaymentReminderJob::dispatch($fixedExpense)->delay($reminderAt);
            }
        }

        return $occurrence;
    }
}
