<?php

namespace App\Contracts;

use App\Models\Budget;

interface UpdateBudgetActionInterface
{
    public function execute(Budget $budget, array $data): Budget;
}
