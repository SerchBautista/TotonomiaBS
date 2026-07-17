<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'profile.view',
            'profile.update',
            'two-factor.update',
            'dashboard.view',
            'files.upload',
            'administrators.view',
            'administrators.create',
            'administrators.update',
            'administrators.delete',
            'users.view',
            'users.assign-plan',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api',
            ]);
        }

        $adminRole = Role::query()->firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'api',
        ]);

        $userRole = Role::query()->firstOrCreate([
            'name' => 'user',
            'guard_name' => 'api',
        ]);

        Role::query()->firstOrCreate([
            'name' => 'premium',
            'guard_name' => 'api',
        ]);

        $adminRole->syncPermissions($permissions);
        $userRole->syncPermissions(['profile.view', 'profile.update', 'two-factor.update', 'files.upload']);
    }
}
