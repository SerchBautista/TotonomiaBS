<?php

namespace App\Actions;

use App\Contracts\DestroyBudgetActionInterface;
use App\Models\Budget;

class DestroyBudgetAction implements DestroyBudgetActionInterface
{
    public function execute(Budget $budget): void
    {
        $budget->delete();
    }
}
