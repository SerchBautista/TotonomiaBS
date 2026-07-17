<?php

namespace App\Contracts;

use App\Models\Expense;
use App\Models\FixedExpenseOccurrence;
use App\Models\User;

interface PayOccurrenceActionInterface
{
    /**
     * Register a payment for a pending or overdue occurrence.
     *
     * @param  array{amount: numeric, payment_type: string, payment_instrument_id?: string|null, paid_at: string}  $data
     */
    public function execute(User $user, FixedExpenseOccurrence $occurrence, array $data): Expense;
}
