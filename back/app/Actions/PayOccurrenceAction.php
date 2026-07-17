<?php

namespace App\Actions;

use App\Contracts\PayOccurrenceActionInterface;
use App\Contracts\RegisterExpenseActionInterface;
use App\Models\Expense;
use App\Models\FixedExpenseOccurrence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayOccurrenceAction implements PayOccurrenceActionInterface
{
    public function __construct(
        private readonly RegisterExpenseActionInterface $registerExpenseAction,
        private readonly GetValidWorkspaceCategoriesAction $getValidWorkspaceCategories,
    ) {}

    /**
     * Register a payment for a pending or overdue occurrence.
     *
     * @param  array{amount: numeric, payment_type: string, payment_instrument_id?: string|null, paid_at: string, paid_by_user_id?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function execute(User $user, FixedExpenseOccurrence $occurrence, array $data): Expense
    {
        if ($occurrence->status === 'paid') {
            throw ValidationException::withMessages([
                'occurrence' => [__('api.validation.occurrence_already_paid')],
            ]);
        }

        $fixedExpense = $occurrence->fixedExpense;

        $isValidCategory = $this->getValidWorkspaceCategories
            ->execute($fixedExpense->workspace)
            ->whereKey($fixedExpense->category_id)
            ->exists();

        if (! $isValidCategory) {
            throw ValidationException::withMessages([
                'category_id' => [__('api.validation.selected_invalid_for_current_workspace', [
                    'attribute' => trans('validation.attributes.category_id'),
                ])],
            ]);
        }

        return DB::transaction(function () use ($user, $fixedExpense, $occurrence, $data): Expense {
            $expense = $this->registerExpenseAction->execute(
                $user,
                $fixedExpense->workspace,
                [
                    'workspace_id' => $fixedExpense->workspace_id,
                    'category_id' => $fixedExpense->category_id,
                    'payment_type' => $data['payment_type'],
                    'payment_instrument_id' => $data['payment_instrument_id'] ?? null,
                    'fixed_expense_id' => $fixedExpense->id,
                    'amount' => $data['amount'],
                    'date' => $data['paid_at'],
                    'description' => $fixedExpense->description,
                    'paid_by_user_id' => $data['paid_by_user_id'] ?? $user->id,
                ]
            );

            $occurrence->update([
                'status' => 'paid',
                'actual_amount' => $data['amount'],
                'payment_type' => $data['payment_type'],
                'payment_instrument_id' => $data['payment_instrument_id'] ?? null,
                'paid_at' => $data['paid_at'],
                'expense_id' => $expense->id,
            ]);

            return $expense;
        });
    }
}
