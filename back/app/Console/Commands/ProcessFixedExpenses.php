<?php

namespace App\Console\Commands;

use App\Actions\ProcessFixedExpensesAction;
use Illuminate\Console\Command;

class ProcessFixedExpenses extends Command
{
    protected $signature = 'expenses:process-fixed';

    protected $description = 'Process all active fixed expenses that are due today or overdue.';

    public function handle(ProcessFixedExpensesAction $action): int
    {
        $this->info('Processing fixed expenses...');

        $result = $action->execute();

        $this->table(
            ['Processed', 'Skipped', 'Failed'],
            [[$result['processed'], $result['skipped'], $result['failed']]]
        );

        if ($result['failed'] > 0) {
            $this->warn("⚠️  {$result['failed']} fixed expense(s) failed. Check the logs.");
        }

        return self::SUCCESS;
    }
}
