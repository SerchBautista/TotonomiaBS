<?php

namespace App\Actions;

use App\Models\Card;
use App\Models\Expense;
use App\Models\FixedExpense;
use App\Models\OtherPaymentMethod;
use App\Models\Workspace;

class CountPaymentMethodUsageAction
{
    public function execute(Card|OtherPaymentMethod $paymentMethod, Workspace $workspace): int
    {
        $paymentType = $paymentMethod instanceof Card ? 'card' : 'other';

        $expenseCount = Expense::query()
            ->where('workspace_id', $workspace->id)
            ->where('payment_type', $paymentType)
            ->where('payment_instrument_id', $paymentMethod->id)
            ->count();

        $fixedExpenseCount = FixedExpense::query()
            ->where('workspace_id', $workspace->id)
            ->where('payment_type', $paymentType)
            ->where('payment_instrument_id', $paymentMethod->id)
            ->count();

        return $expenseCount + $fixedExpenseCount;
    }
}
