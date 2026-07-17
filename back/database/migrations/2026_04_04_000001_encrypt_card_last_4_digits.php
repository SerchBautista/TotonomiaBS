<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->text('last_4_digits')->nullable()->change();
        });

        DB::table('cards')
            ->whereNotNull('last_4_digits')
            ->orderBy('id')
            ->each(function ($card) {
                DB::table('cards')
                    ->where('id', $card->id)
                    ->update(['last_4_digits' => Crypt::encrypt($card->last_4_digits)]);
            });
    }

    public function down(): void
    {
        DB::table('cards')
            ->whereNotNull('last_4_digits')
            ->orderBy('id')
            ->each(function ($card) {
                try {
                    $decrypted = Crypt::decrypt($card->last_4_digits);
                    DB::table('cards')
                        ->where('id', $card->id)
                        ->update(['last_4_digits' => $decrypted]);
                } catch (\Throwable) {
                    // Already plaintext, skip
                }
            });

        Schema::table('cards', function (Blueprint $table) {
            $table->string('last_4_digits')->nullable()->change();
        });
    }
};
