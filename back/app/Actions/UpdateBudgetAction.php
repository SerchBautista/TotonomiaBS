<?php

namespace App\Actions;

use App\Contracts\UpdateBudgetActionInterface;
use App\Models\Budget;

class UpdateBudgetAction implements UpdateBudgetActionInterface
{
    public function execute(Budget $budget, array $data): Budget
    {
        $updatable = [];

        if (array_key_exists('amount', $data)) {
            $updatable['amount'] = $data['amount'];
        }
        if (array_key_exists('alert_threshold', $data)) {
            $updatable['alert_threshold'] = $data['alert_threshold'];
        }
        if (array_key_exists('alert_enabled', $data)) {
            $updatable['alert_enabled'] = $data['alert_enabled'];
        }

        if (! empty($updatable)) {
            $budget->update($updatable);
        }

        return $budget->fresh('category');
    }
}
