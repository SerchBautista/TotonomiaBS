<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_user', function (Blueprint $table) {
            $table->boolean('can_add_fixed_expenses')->default(false)->after('role');
            $table->boolean('can_add_categories')->default(false)->after('can_add_fixed_expenses');
        });
    }

    public function down(): void
    {
        Schema::table('workspace_user', function (Blueprint $table) {
            $table->dropColumn(['can_add_fixed_expenses', 'can_add_categories']);
        });
    }
};
