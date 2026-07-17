<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->uuid('category_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('effective_from');
            $table->decimal('alert_threshold', 3, 2)->default(0.80);
            $table->boolean('alert_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('workspace_id')
                ->references('id')->on('workspaces')
                ->cascadeOnDelete();

            $table->foreign('category_id')
                ->references('id')->on('categories')
                ->nullOnDelete();

            $table->unique(['workspace_id', 'category_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
