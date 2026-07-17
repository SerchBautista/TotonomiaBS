<?php

namespace App\Actions;

use App\Contracts\RegisterExpenseActionInterface;
use App\Models\Expense;
use App\Models\User;
use App\Models\Workspace;

class RegisterExpenseAction implements RegisterExpenseActionInterface
{
    /**
     * Register a new expense in a workspace.
     *
     * @param  array{
     *   id?: string,
     *   category_id: string,
     *   payment_type: string,
     *   payment_instrument_id?: string|null,
     *   amount: numeric,
     *   date: string,
     *   description?: string|null,
     *   fixed_expense_id?: string|null,
     *   paid_by_user_id?: string|null
     * }  $data
     */
    public function execute(User $user, Workspace $workspace, array $data): Expense
    {
        $expense = Expense::create([
            'id' => $data['id'] ?? null, // Offline-First: client may supply UUID
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'paid_by_user_id' => $data['paid_by_user_id'] ?? null,
            'category_id' => $data['category_id'],
            'payment_type' => $data['payment_type'],
            'payment_instrument_id' => $data['payment_instrument_id'] ?? null,
            'fixed_expense_id' => $data['fixed_expense_id'] ?? null,
            'amount' => $data['amount'],
            'date' => $data['date'],
            'description' => $data['description'] ?? null,
        ]);

        return $expense->load('category', 'paymentInstrument', 'user', 'paidBy');
    }
}
