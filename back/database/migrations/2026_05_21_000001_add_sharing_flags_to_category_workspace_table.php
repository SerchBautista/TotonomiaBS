<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_workspace', function (Blueprint $table) {
            $table->boolean('is_shared')->default(true)->after('workspace_id');
            $table->boolean('is_active')->default(true)->after('is_shared');
        });
    }

    public function down(): void
    {
        Schema::table('category_workspace', function (Blueprint $table) {
            $table->dropColumn(['is_shared', 'is_active']);
        });
    }
};
