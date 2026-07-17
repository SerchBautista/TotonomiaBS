<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->date('month');
            $table->uuid('from_category_id');
            $table->uuid('to_category_id');
            $table->decimal('amount', 15, 2);
            $table->string('reason')->nullable();
            $table->foreignUuid('user_id')->constrained('users');
            $table->timestamps();

            $table->index(['workspace_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_adjustments');
    }
};
