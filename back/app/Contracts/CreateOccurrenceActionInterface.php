<?php

namespace App\Contracts;

use App\Models\FixedExpense;
use App\Models\FixedExpenseOccurrence;

interface CreateOccurrenceActionInterface
{
    /**
     * Create a pending occurrence for a fixed expense.
     * Idempotent: returns existing occurrence if one already exists for the same due date.
     */
    public function execute(FixedExpense $fixedExpense): FixedExpenseOccurrence;
}
