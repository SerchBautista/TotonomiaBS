<?php

namespace App\Actions;

use App\Contracts\BudgetStatusActionInterface;
use App\Contracts\CalculateEffectiveBudgetActionInterface;
use App\Models\Budget;
use App\Models\BudgetAdjustment;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BudgetStatusAction implements BudgetStatusActionInterface
{
    public function __construct(
        private readonly CalculateEffectiveBudgetActionInterface $effectiveBudgetAction,
    ) {}

    public function execute(Workspace $workspace, ?Carbon $month): array
    {
        $month = $month ?? Carbon::now();
        $monthStart = $month->copy()->startOfMonth()->toDateString();
        $monthEnd = $month->copy()->endOfMonth()->toDateString();
        $monthLabel = $month->format('Y-m');

        $spentByCategory = $workspace->expenses()
            ->whereDate('date', '>=', $monthStart)
            ->whereDate('date', '<=', $monthEnd)
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->pluck('total', 'category_id')
            ->map(fn ($v) => (float) $v);

        $totalSpent = $spentByCategory->sum();

        $committedByCategory = \App\Models\FixedExpense::where('workspace_id', $workspace->id)
            ->where('is_active', true)
            ->whereDate('next_due_date', '>=', $monthStart)
            ->whereDate('next_due_date', '<=', $monthEnd)
            ->whereDoesntHave('generatedExpenses', function ($query) use ($monthStart, $monthEnd) {
                $query->whereDate('date', '>=', $monthStart)
                    ->whereDate('date', '<=', $monthEnd);
            })
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->pluck('total', 'category_id')
            ->map(fn ($v) => (float) $v);

        $generalBudget = Budget::currentFor($workspace, null, $month);
        $general = null;

        if ($generalBudget) {
            $budgetAmount = (float) $generalBudget->amount;
            $generalCommitted = (float) $committedByCategory->sum();
            $generalEffectiveSpent = $totalSpent + $generalCommitted;
            $percentage = $budgetAmount > 0 ? $generalEffectiveSpent / $budgetAmount : 0;
            $alertThreshold = (float) $generalBudget->alert_threshold;

            $general = [
                'id' => $generalBudget->id,
                'budget' => number_format($budgetAmount, 2, '.', ''),
                'spent' => number_format($totalSpent, 2, '.', ''),
                'committed' => number_format($generalCommitted, 2, '.', ''),
                'effective_spent' => number_format($generalEffectiveSpent, 2, '.', ''),
                'remaining' => number_format(max(0, $budgetAmount - $totalSpent - $generalCommitted), 2, '.', ''),
                'percentage' => round($percentage, 4),
                'alert_threshold' => $alertThreshold,
                'alert_enabled' => $generalBudget->alert_enabled,
                'over_threshold' => $alertThreshold > 0 && $generalEffectiveSpent >= $alertThreshold,
            ];
        }

        $categoryIdsWithActivity = $spentByCategory->keys()
            ->merge($committedByCategory->keys())
            ->filter(fn ($id) => $id !== null)
            ->unique();

        $categoryBudgets = Budget::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('category_id')
            ->effectiveFor($month)
            ->with('category')
            ->get()
            ->unique('category_id');

        $allCategoryIds = $categoryBudgets->pluck('category_id')
            ->merge($categoryIdsWithActivity)
            ->filter()
            ->unique();

        $adjustmentsByCategory = $this->getAdjustmentsByCategory($workspace, $monthStart);

        $categories = collect($allCategoryIds)->map(function ($categoryId) use ($categoryBudgets, $spentByCategory, $committedByCategory, $adjustmentsByCategory) {
            $category = $categoryBudgets->firstWhere('category_id', $categoryId)?->category;
            if (! $category) {
                $category = \App\Models\Category::find($categoryId);
            }
            $hasBudget = $categoryBudgets->contains('category_id', $categoryId);
            $budget = $hasBudget ? $categoryBudgets->firstWhere('category_id', $categoryId) : null;

            $spent = (float) ($spentByCategory->get($categoryId) ?? 0);
            $committed = (float) ($committedByCategory->get($categoryId) ?? 0);
            $effectiveSpent = $spent + $committed;

            if ($hasBudget && $budget) {
                $budgetAmount = (float) $budget->amount;
                $catAdjustments = $adjustmentsByCategory->get($categoryId) ?? ['in' => 0, 'out' => 0, 'items' => []];
                $adjustmentsIn = $catAdjustments['in'];
                $adjustmentsOut = $catAdjustments['out'];
                $effectiveBudget = max(0, $budgetAmount + $adjustmentsIn - $adjustmentsOut);
                $remaining = max(0, $effectiveBudget - $spent - $committed);
                $percentage = $effectiveBudget > 0 ? $effectiveSpent / $effectiveBudget : 0;
                $catAlertThreshold = (float) $budget->alert_threshold;

                return [
                    'category_id' => $categoryId,
                    'category_name' => $category?->name,
                    'category_icon' => $category?->icon,
                    'category_color' => $category?->color,
                    'has_budget' => true,
                    'id' => $budget->id,
                    'base_budget' => number_format($budgetAmount, 2, '.', ''),
                    'adjustments_in' => number_format($adjustmentsIn, 2, '.', ''),
                    'adjustments_out' => number_format($adjustmentsOut, 2, '.', ''),
                    'effective_budget' => number_format($effectiveBudget, 2, '.', ''),
                    'spent' => number_format($spent, 2, '.', ''),
                    'committed' => number_format($committed, 2, '.', ''),
                    'effective_spent' => number_format($effectiveSpent, 2, '.', ''),
                    'remaining' => number_format($remaining, 2, '.', ''),
                    'percentage' => round($percentage, 4),
                    'alert_threshold' => $catAlertThreshold,
                    'alert_enabled' => $budget->alert_enabled,
                    'over_threshold' => $catAlertThreshold > 0 && $effectiveSpent >= $catAlertThreshold,
                    'over_budget' => $effectiveSpent > $effectiveBudget,
                    'adjustments' => $catAdjustments['items'],
                ];
            }

            return [
                'category_id' => $categoryId,
                'category_name' => $category?->name,
                'category_icon' => $category?->icon,
                'category_color' => $category?->color,
                'has_budget' => false,
                'spent' => number_format($spent, 2, '.', ''),
                'committed' => number_format($committed, 2, '.', ''),
                'effective_spent' => number_format($effectiveSpent, 2, '.', ''),
            ];
        })->values()->all();

        return [
            'month' => $monthLabel,
            'general' => $general,
            'categories' => $categories,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<string, array{in: float, out: float, items: array<int, array<string, mixed>>}>
     */
    private function getAdjustmentsByCategory(Workspace $workspace, string $monthStart)
    {
        $adjustments = BudgetAdjustment::query()
            ->where('workspace_id', $workspace->id)
            ->whereDate('month', $monthStart)
            ->with(['fromCategory', 'toCategory'])
            ->get();

        $result = [];

        foreach ($adjustments as $adj) {
            $toId = $adj->to_category_id;
            $fromId = $adj->from_category_id;

            if (! isset($result[$toId])) {
                $result[$toId] = ['in' => 0, 'out' => 0, 'items' => []];
            }
            if (! isset($result[$fromId])) {
                $result[$fromId] = ['in' => 0, 'out' => 0, 'items' => []];
            }

            $result[$toId]['in'] += (float) $adj->amount;
            $result[$fromId]['out'] += (float) $adj->amount;

            $item = [
                'id' => $adj->id,
                'amount' => number_format((float) $adj->amount, 2, '.', ''),
                'reason' => $adj->reason,
                'from_category_id' => $adj->from_category_id,
                'from_category_name' => $adj->fromCategory?->name,
                'to_category_id' => $adj->to_category_id,
                'to_category_name' => $adj->toCategory?->name,
                'created_at' => $adj->created_at?->toIso8601String(),
            ];

            $result[$toId]['items'][] = $item;
            $result[$fromId]['items'][] = $item;
        }

        return collect($result);
    }
}
