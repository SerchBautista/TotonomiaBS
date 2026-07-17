<?php

use App\Console\Commands\MarkOverdueOccurrences;
use App\Console\Commands\ProcessFixedExpenses;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Process fixed/recurring expenses once daily at midnight
Schedule::command(ProcessFixedExpenses::class)->dailyAt('00:05')->withoutOverlapping();

// Mark overdue occurrences daily after processing
Schedule::command(MarkOverdueOccurrences::class)->dailyAt('00:10')->withoutOverlapping();
