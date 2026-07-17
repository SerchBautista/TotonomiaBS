<?php

namespace App\Actions;

use App\Models\Expense;
use App\Notifications\ExpenseAddedToSharedWorkspaceNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotifySharedWorkspaceMembersOfExpenseAction
{
    /**
     * Notify workspace members (excluding the expense author) about a new
     * expense, but only when the workspace is shared (more than one member).
     *
     * No-op when:
     *  - the workspace is personal (single member), or
     *  - the expense has no workspace (defensive).
     */
    public function execute(Expense $expense): void
    {
        $workspace = $expense->workspace;

        if ($workspace === null) {
            return;
        }

        $memberCount = $workspace->members()->count();

        if ($memberCount <= 1) {
            return;
        }

        $expense->loadMissing('user', 'workspace');

        $notification = new ExpenseAddedToSharedWorkspaceNotification($expense);

        foreach ($workspace->members as $member) {
            if ($member->id === $expense->user_id) {
                continue;
            }

            try {
                $member->notify($notification);
            } catch (Throwable $exception) {
                Log::warning('Failed to notify workspace member of new expense.', [
                    'user_id' => $member->id,
                    'expense_id' => $expense->id,
                    'exception' => $exception->getMessage(),
                ]);
            }
        }
    }
}
