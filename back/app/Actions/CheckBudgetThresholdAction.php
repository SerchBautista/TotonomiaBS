<?php

namespace App\Actions;

use App\Contracts\CalculateEffectiveBudgetActionInterface;
use App\Contracts\CheckBudgetThresholdActionInterface;
use App\Models\Budget;
use App\Models\Workspace;
use Carbon\Carbon;

class CheckBudgetThresholdAction implements CheckBudgetThresholdActionInterface
{
    public function __construct(
        private readonly CalculateEffectiveBudgetActionInterface $effectiveBudgetAction,
    ) {}

    public function execute(Workspace $workspace, string $categoryId, string $date): array
    {
        $month = Carbon::parse($date);
        $monthStart = $month->copy()->startOfMonth()->toDateString();
        $monthEnd = $month->copy()->endOfMonth()->toDateString();

        $warnings = [];

        // Total spent for the month (general check)
        $totalSpent = (float) $workspace->expenses()
            ->whereDate('date', '>=', $monthStart)
            ->whereDate('date', '<=', $monthEnd)
            ->sum('amount');

        $generalBudget = Budget::currentFor($workspace, null, $month);

        if ($generalBudget && $generalBudget->alert_enabled) {
            $budgetAmount = (float) $generalBudget->amount;
            $threshold = (float) $generalBudget->alert_threshold;
            $percentage = $budgetAmount > 0 ? $totalSpent / $budgetAmount : 0;

            if ($threshold > 0 && $totalSpent >= $threshold) {
                $warnings[] = [
                    'scope' => 'general',
                    'budget' => number_format($budgetAmount, 2, '.', ''),
                    'spent' => number_format($totalSpent, 2, '.', ''),
                    'percentage' => round($percentage, 4),
                    'threshold' => $threshold,
                ];
            }
        }

        // Category-specific check
        $categoryBudget = Budget::currentFor($workspace, $categoryId, $month);

        if ($categoryBudget && $categoryBudget->alert_enabled) {
            $baseAmount = (float) $categoryBudget->amount;
            $threshold = (float) $categoryBudget->alert_threshold;

            $categorySpent = (float) $workspace->expenses()
                ->whereDate('date', '>=', $monthStart)
                ->whereDate('date', '<=', $monthEnd)
                ->where('category_id', $categoryId)
                ->sum('amount');

            $effective = $this->effectiveBudgetAction->execute($workspace, $categoryId, $month);
            $effectiveBudget = $effective['effective_budget'];

            $percentage = $effectiveBudget > 0 ? $categorySpent / $effectiveBudget : 0;

            $warning = [
                'scope' => 'category',
                'category_name' => $categoryBudget->category?->name,
                'budget' => number_format($baseAmount, 2, '.', ''),
                'effective_budget' => number_format($effectiveBudget, 2, '.', ''),
                'spent' => number_format($categorySpent, 2, '.', ''),
                'percentage' => round($percentage, 4),
                'threshold' => $threshold,
                'over_budget' => $categorySpent > $effectiveBudget,
            ];

            if ($threshold > 0 && $categorySpent >= $threshold) {
                $categoryBudget->load('category');
                $warnings[] = $warning;
            } elseif ($categorySpent > $effectiveBudget) {
                // Always warn when over budget, even if threshold wasn't crossed
                $categoryBudget->load('category');
                $warnings[] = $warning;
            }
        }

        return $warnings;
    }
}
