<?php

namespace App\Actions;

use App\Contracts\CalculateEffectiveBudgetActionInterface;
use App\Models\Budget;
use App\Models\BudgetAdjustment;
use App\Models\Workspace;
use Carbon\Carbon;

class CalculateEffectiveBudgetAction implements CalculateEffectiveBudgetActionInterface
{
    public function execute(Workspace $workspace, string $categoryId, Carbon $month): array
    {
        $monthStart = $month->copy()->startOfMonth()->toDateString();

        $baseBudget = Budget::currentFor($workspace, $categoryId, $month);
        $baseAmount = $baseBudget ? (float) $baseBudget->amount : 0;

        $adjustmentsIn = (float) BudgetAdjustment::query()
            ->where('workspace_id', $workspace->id)
            ->where('to_category_id', $categoryId)
            ->whereDate('month', $monthStart)
            ->sum('amount');

        $adjustmentsOut = (float) BudgetAdjustment::query()
            ->where('workspace_id', $workspace->id)
            ->where('from_category_id', $categoryId)
            ->whereDate('month', $monthStart)
            ->sum('amount');

        $effectiveBudget = $baseAmount + $adjustmentsIn - $adjustmentsOut;

        return [
            'base_amount' => $baseAmount,
            'adjustments_in' => $adjustmentsIn,
            'adjustments_out' => $adjustmentsOut,
            'effective_budget' => max(0, $effectiveBudget),
        ];
    }
}
