<?php

namespace App\Actions;

use App\Models\Budget;
use App\Models\BudgetAdjustment;
use App\Models\Category;
use App\Models\Expense;
use App\Models\FixedExpense;
use App\Models\Workspace;

class CountCategoryUsageAction
{
    public function execute(Category $category, ?Workspace $workspace = null): int
    {
        $workspaceId = $workspace?->id;

        $expenseCount = Expense::query()
            ->where('category_id', $category->id)
            ->when($workspaceId, fn ($query) => $query->where('workspace_id', $workspaceId))
            ->count();

        $fixedExpenseCount = FixedExpense::query()
            ->where('category_id', $category->id)
            ->when($workspaceId, fn ($query) => $query->where('workspace_id', $workspaceId))
            ->count();

        $budgetCount = Budget::query()
            ->where('category_id', $category->id)
            ->when($workspaceId, fn ($query) => $query->where('workspace_id', $workspaceId))
            ->count();

        $adjustmentCount = BudgetAdjustment::query()
            ->where(function ($query) use ($category): void {
                $query->where('from_category_id', $category->id)
                    ->orWhere('to_category_id', $category->id);
            })
            ->when($workspaceId, fn ($query) => $query->where('workspace_id', $workspaceId))
            ->count();

        return $expenseCount + $fixedExpenseCount + $budgetCount + $adjustmentCount;
    }
}
