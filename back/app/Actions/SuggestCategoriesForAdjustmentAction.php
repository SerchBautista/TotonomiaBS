<?php

namespace App\Actions;

use App\Contracts\SuggestCategoriesForAdjustmentActionInterface;
use App\Models\Budget;
use App\Models\BudgetAdjustment;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SuggestCategoriesForAdjustmentAction implements SuggestCategoriesForAdjustmentActionInterface
{
    public function execute(Workspace $workspace, string $excludeCategoryId, Carbon $month): array
    {
        $monthStart = $month->copy()->startOfMonth()->toDateString();
        $monthEnd = $month->copy()->endOfMonth()->toDateString();

        $categoryBudgets = Budget::query()
            ->where('workspace_id', $workspace->id)
            ->whereNotNull('category_id')
            ->where('category_id', '!=', $excludeCategoryId)
            ->whereHas('category.workspaces', function ($q) use ($workspace) {
                $q->where('workspaces.id', $workspace->id)
                    ->where('category_workspace.is_active', true);
            })
            ->effectiveFor($month)
            ->with('category')
            ->get()
            ->unique('category_id');

        $spentByCategory = $workspace->expenses()
            ->whereDate('date', '>=', $monthStart)
            ->whereDate('date', '<=', $monthEnd)
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->pluck('total', 'category_id')
            ->map(fn ($v) => (float) $v);

        $adjustmentsIn = BudgetAdjustment::query()
            ->where('workspace_id', $workspace->id)
            ->whereDate('month', $monthStart)
            ->select('to_category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('to_category_id')
            ->pluck('total', 'to_category_id')
            ->map(fn ($v) => (float) $v);

        $adjustmentsOut = BudgetAdjustment::query()
            ->where('workspace_id', $workspace->id)
            ->whereDate('month', $monthStart)
            ->select('from_category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('from_category_id')
            ->pluck('total', 'from_category_id')
            ->map(fn ($v) => (float) $v);

        $suggestions = [];

        foreach ($categoryBudgets as $budget) {
            $catId = $budget->category_id;
            $baseAmount = (float) $budget->amount;
            $spent = (float) ($spentByCategory->get($catId) ?? 0);
            $in = (float) ($adjustmentsIn->get($catId) ?? 0);
            $out = (float) ($adjustmentsOut->get($catId) ?? 0);
            $effective = $baseAmount + $in - $out;
            $available = max(0, $effective - $spent);

            if ($available > 0) {
                $suggestions[] = [
                    'category_id' => $catId,
                    'category_name' => $budget->category?->name,
                    'category_icon' => $budget->category?->icon,
                    'category_color' => $budget->category?->color,
                    'base_budget' => number_format($baseAmount, 2, '.', ''),
                    'effective_budget' => number_format($effective, 2, '.', ''),
                    'spent' => number_format($spent, 2, '.', ''),
                    'available' => number_format($available, 2, '.', ''),
                ];
            }
        }

        usort($suggestions, fn ($a, $b) => (float) $b['available'] <=> (float) $a['available']);

        return $suggestions;
    }
}
