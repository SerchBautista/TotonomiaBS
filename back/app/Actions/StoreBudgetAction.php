<?php

namespace App\Actions;

use App\Contracts\StoreBudgetActionInterface;
use App\Models\Budget;
use App\Models\Workspace;
use Carbon\Carbon;

class StoreBudgetAction implements StoreBudgetActionInterface
{
    public function execute(Workspace $workspace, array $data): Budget
    {
        $budget = $workspace->budgets()->create([
            'category_id' => $data['category_id'] ?? null,
            'amount' => $data['amount'],
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
            'alert_threshold' => $data['alert_threshold'] ?? 0,
            'alert_enabled' => $data['alert_enabled'] ?? true,
        ]);

        return $budget->load('category');
    }
}
