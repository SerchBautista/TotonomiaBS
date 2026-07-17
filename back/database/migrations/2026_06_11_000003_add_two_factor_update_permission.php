<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $permissionTable = config('permission.table_names.permissions', 'permissions');
        $roleTable = config('permission.table_names.roles', 'roles');
        $roleHasPermissionsTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');

        // Insert permission if it doesn't exist
        $permissionExists = DB::table($permissionTable)
            ->where('name', 'two-factor.update')
            ->where('guard_name', 'api')
            ->exists();

        if (! $permissionExists) {
            DB::table($permissionTable)->insert([
                'name' => 'two-factor.update',
                'guard_name' => 'api',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Get permission id
        $permissionId = DB::table($permissionTable)
            ->where('name', 'two-factor.update')
            ->where('guard_name', 'api')
            ->value('id');

        // Get user role id
        $roleId = DB::table($roleTable)
            ->where('name', 'user')
            ->where('guard_name', 'api')
            ->value('id');

        if ($permissionId && $roleId) {
            $relationExists = DB::table($roleHasPermissionsTable)
                ->where('permission_id', $permissionId)
                ->where('role_id', $roleId)
                ->exists();

            if (! $relationExists) {
                DB::table($roleHasPermissionsTable)->insert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }

        // Clear permission cache
        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $permissionTable = config('permission.table_names.permissions', 'permissions');
        $roleHasPermissionsTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');

        $permissionId = DB::table($permissionTable)
            ->where('name', 'two-factor.update')
            ->where('guard_name', 'api')
            ->value('id');

        if ($permissionId) {
            DB::table($roleHasPermissionsTable)
                ->where('permission_id', $permissionId)
                ->delete();

            DB::table($permissionTable)
                ->where('id', $permissionId)
                ->delete();
        }

        // Clear permission cache
        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};
