<?php

namespace App\Contracts;

use App\Models\Expense;
use App\Models\User;
use App\Models\Workspace;

interface RegisterExpenseActionInterface
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
    public function execute(User $user, Workspace $workspace, array $data): Expense;
}
