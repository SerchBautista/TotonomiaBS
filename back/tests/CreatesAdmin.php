<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Passport\Passport;

trait CreatesAdmin
{
    protected function seedRolesAndPermissions(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function createAdminUser(array $attributes = []): User
    {
        $admin = User::factory()->create($attributes);
        $admin->assignRole('admin');

        return $admin;
    }

    protected function actingAsAdmin(User $admin): static
    {
        Passport::actingAs($admin, ['*'], 'api');

        return $this;
    }
}
