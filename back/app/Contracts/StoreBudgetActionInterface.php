<?php

namespace App\Contracts;

use App\Models\Budget;
use App\Models\Workspace;

interface StoreBudgetActionInterface
{
    public function execute(Workspace $workspace, array $data): Budget;
}
