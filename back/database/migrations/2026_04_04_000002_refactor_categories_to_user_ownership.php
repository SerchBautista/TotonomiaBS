<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1.1 Add user_id (nullable for now, while we migrate data)
        Schema::table('categories', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->after('id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        // 1.2 Data-migrate: user_id = workspace.owner_id for each category
        DB::table('categories')
            ->whereNotNull('workspace_id')
            ->orderBy('id')
            ->each(function ($category) {
                $workspace = DB::table('workspaces')->where('id', $category->workspace_id)->first();
                if ($workspace) {
                    DB::table('categories')
                        ->where('id', $category->id)
                        ->update(['user_id' => $workspace->owner_id]);
                }
            });

        // 1.3 Create category_workspace pivot table
        Schema::create('category_workspace', function (Blueprint $table) {
            $table->uuid('category_id');
            $table->uuid('workspace_id');

            $table->primary(['category_id', 'workspace_id']);

            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
        });

        // 1.4 Data-migrate to pivot: existing categories are enabled in their original workspace
        DB::table('categories')
            ->whereNotNull('workspace_id')
            ->orderBy('id')
            ->each(function ($category) {
                DB::table('category_workspace')->insertOrIgnore([
                    'category_id' => $category->id,
                    'workspace_id' => $category->workspace_id,
                ]);
            });

        // 1.5 Make user_id NOT NULL and drop workspace_id
        Schema::table('categories', function (Blueprint $table) {
            $table->uuid('user_id')->nullable(false)->change();
            $table->dropForeign(['workspace_id']);
            $table->dropColumn('workspace_id');
        });
    }

    public function down(): void
    {
        // Restore workspace_id (nullable)
        Schema::table('categories', function (Blueprint $table) {
            $table->uuid('workspace_id')->nullable()->after('id');
        });

        // Restore workspace_id from pivot (pick first workspace per category)
        DB::table('category_workspace')
            ->orderBy('category_id')
            ->get()
            ->groupBy('category_id')
            ->each(function ($rows, $categoryId) {
                DB::table('categories')
                    ->where('id', $categoryId)
                    ->update(['workspace_id' => $rows->first()->workspace_id]);
            });

        // Add back FK after data is populated
        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
        });

        // Drop pivot
        Schema::dropIfExists('category_workspace');

        // Drop user_id
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
