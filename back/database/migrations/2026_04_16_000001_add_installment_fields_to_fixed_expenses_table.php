<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_expenses', function (Blueprint $table) {
            $table->string('type')->default('recurring')->after('reminders_enabled');
            $table->unsignedSmallInteger('total_installments')->nullable()->after('type');
            $table->unsignedSmallInteger('remaining_installments')->nullable()->after('total_installments');
        });
    }

    public function down(): void
    {
        Schema::table('fixed_expenses', function (Blueprint $table) {
            $table->dropColumn(['type', 'total_installments', 'remaining_installments']);
        });
    }
};
