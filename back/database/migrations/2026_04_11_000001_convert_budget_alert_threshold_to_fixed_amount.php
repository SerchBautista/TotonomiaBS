<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. First widen the column so it can hold fixed amounts
        Schema::table('budgets', function (Blueprint $table) {
            $table->decimal('alert_threshold', 15, 2)->default(0)->change();
        });

        // 2. Convert existing percentage-based thresholds to fixed amounts
        //    e.g. amount=2000, alert_threshold=0.80 → alert_threshold=1600.00
        DB::table('budgets')->whereNull('deleted_at')->orderBy('id')->chunk(100, function ($budgets) {
            foreach ($budgets as $budget) {
                $amount = (float) $budget->amount;
                $threshold = (float) $budget->alert_threshold;

                // Only convert values that look like percentages (0–1 range)
                if ($threshold >= 0 && $threshold <= 1) {
                    $fixedAmount = round($amount * $threshold, 2);

                    DB::table('budgets')
                        ->where('id', $budget->id)
                        ->update(['alert_threshold' => $fixedAmount]);
                }
            }
        });
    }

    public function down(): void
    {
        // Convert fixed amounts back to percentages
        DB::table('budgets')->whereNull('deleted_at')->orderBy('id')->chunk(100, function ($budgets) {
            foreach ($budgets as $budget) {
                $amount = (float) $budget->amount;
                $threshold = (float) $budget->alert_threshold;
                $percentage = $amount > 0 ? round($threshold / $amount, 2) : 0.80;
                $percentage = min($percentage, 0.99);

                DB::table('budgets')
                    ->where('id', $budget->id)
                    ->update(['alert_threshold' => $percentage]);
            }
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->decimal('alert_threshold', 3, 2)->default(0.80)->change();
        });
    }
};
