<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Assign 'owner' to workspace owners, 'guest' to everyone else
        DB::statement('
            UPDATE workspace_user
            SET role = CASE
                WHEN user_id = (SELECT owner_id FROM workspaces WHERE workspaces.id = workspace_user.workspace_id)
                THEN \'owner\'
                ELSE \'guest\'
            END
        ');

        // Change the default for new invites
        Schema::table('workspace_user', function (Blueprint $table) {
            $table->string('role')->default('guest')->change();
        });
    }

    public function down(): void
    {
        // Restore previous defaults and rough role mapping
        DB::statement('
            UPDATE workspace_user
            SET role = CASE
                WHEN user_id = (SELECT owner_id FROM workspaces WHERE workspaces.id = workspace_user.workspace_id)
                THEN \'admin\'
                ELSE \'editor\'
            END
        ');

        Schema::table('workspace_user', function (Blueprint $table) {
            $table->string('role')->default('viewer')->change();
        });
    }
};
