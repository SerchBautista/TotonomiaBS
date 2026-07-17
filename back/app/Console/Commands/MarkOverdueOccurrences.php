<?php

namespace App\Console\Commands;

use App\Contracts\MarkOverdueOccurrencesActionInterface;
use Illuminate\Console\Command;

class MarkOverdueOccurrences extends Command
{
    protected $signature = 'occurrences:mark-overdue';

    protected $description = 'Mark pending fixed expense occurrences as overdue when past their due date';

    public function __construct(
        private readonly MarkOverdueOccurrencesActionInterface $markOverdueAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->markOverdueAction->execute();

        $this->info("Marked {$count} occurrence(s) as overdue.");

        return Command::SUCCESS;
    }
}
