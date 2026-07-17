<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->after('workspace_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('user_id');
        });

        Schema::table('other_payment_methods', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->after('workspace_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('user_id');
        });

        Schema::create('card_workspace', function (Blueprint $table) {
            $table->uuid('workspace_id');
            $table->uuid('card_id');
            $table->boolean('is_shared')->default(true);
            $table->boolean('is_active')->default(true);

            $table->primary(['workspace_id', 'card_id']);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('card_id')->references('id')->on('cards')->cascadeOnDelete();
        });

        Schema::create('other_payment_method_workspace', function (Blueprint $table) {
            $table->uuid('workspace_id');
            $table->uuid('other_payment_method_id');
            $table->boolean('is_shared')->default(true);
            $table->boolean('is_active')->default(true);

            $table->primary(['workspace_id', 'other_payment_method_id']);
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreign('other_payment_method_id')->references('id')->on('other_payment_methods')->cascadeOnDelete();
        });

        $cards = DB::table('cards')
            ->join('workspaces', 'workspaces.id', '=', 'cards.workspace_id')
            ->select('cards.id', 'cards.workspace_id', 'workspaces.owner_id')
            ->get();

        foreach ($cards as $card) {
            DB::table('cards')
                ->where('id', $card->id)
                ->update(['user_id' => $card->owner_id]);

            DB::table('card_workspace')->insertOrIgnore([
                'workspace_id' => $card->workspace_id,
                'card_id' => $card->id,
                'is_shared' => true,
                'is_active' => true,
            ]);
        }

        $otherMethods = DB::table('other_payment_methods')
            ->join('workspaces', 'workspaces.id', '=', 'other_payment_methods.workspace_id')
            ->select('other_payment_methods.id', 'other_payment_methods.workspace_id', 'workspaces.owner_id')
            ->get();

        foreach ($otherMethods as $method) {
            DB::table('other_payment_methods')
                ->where('id', $method->id)
                ->update(['user_id' => $method->owner_id]);

            DB::table('other_payment_method_workspace')->insertOrIgnore([
                'workspace_id' => $method->workspace_id,
                'other_payment_method_id' => $method->id,
                'is_shared' => true,
                'is_active' => true,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('other_payment_method_workspace');
        Schema::dropIfExists('card_workspace');

        Schema::table('other_payment_methods', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
