<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_expense_occurrences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('fixed_expense_id');
            $table->date('due_date');
            $table->decimal('suggested_amount', 15, 2);
            $table->decimal('actual_amount', 15, 2)->nullable();
            $table->string('payment_type')->nullable();
            $table->uuid('payment_instrument_id')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->string('status')->default('pending'); // pending, paid, overdue
            $table->uuid('expense_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('fixed_expense_id')
                ->references('id')->on('fixed_expenses')
                ->cascadeOnDelete();

            $table->foreign('expense_id')
                ->references('id')->on('expenses')
                ->nullOnDelete();

            $table->unique(['fixed_expense_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_expense_occurrences');
    }
};
