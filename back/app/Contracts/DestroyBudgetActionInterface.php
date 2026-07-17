<?php

namespace App\Contracts;

use App\Models\Budget;

interface DestroyBudgetActionInterface
{
    public function execute(Budget $budget): void;
}
