<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->foreignUuid('user_id')->constrained('users');
            $table->uuid('category_id');
            $table->uuid('payment_method_id');
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->string('frequency'); // daily, weekly, monthly, yearly
            $table->date('next_due_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_expenses');
    }
};
