<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DedupeUserRolesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
    }

    public function test_dry_run_lists_dual_role_users_without_modifying_them(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(['user', 'admin']);

        $this->artisan('users:dedupe-roles')
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain($admin->email)
            ->assertSuccessful();

        $this->assertTrue($admin->fresh()->hasRole('user'));
        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_apply_flag_removes_user_role_from_dual_role_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(['user', 'admin']);

        $this->artisan('users:dedupe-roles', ['--apply' => true])
            ->expectsOutputToContain('removed')
            ->assertSuccessful();

        $admin->refresh();
        $this->assertFalse($admin->hasRole('user'));
        $this->assertTrue($admin->hasRole('admin'));
    }

    public function test_apply_flag_is_idempotent_on_already_clean_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->artisan('users:dedupe-roles', ['--apply' => true])
            ->expectsOutputToContain('No users with both')
            ->assertSuccessful();

        $this->assertTrue($admin->fresh()->hasRole('admin'));
        $this->assertFalse($admin->fresh()->hasRole('user'));
    }

    public function test_user_filter_targets_only_specific_user(): void
    {
        $target = User::factory()->create();
        $target->assignRole(['user', 'admin']);

        $other = User::factory()->create();
        $other->assignRole(['user', 'admin']);

        $this->artisan('users:dedupe-roles', ['--user' => $target->id, '--apply' => true])
            ->assertSuccessful();

        $this->assertFalse($target->fresh()->hasRole('user'));
        $this->assertTrue($other->fresh()->hasRole('user'));
    }
}
