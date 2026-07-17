<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;
use Tests\CreatesAdmin;
use Tests\TestCase;

class AdministratorAuthorizationTest extends TestCase
{
    use CreatesAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    /**
     * Strip a single permission from the admin role for the duration of a
     * test so we can exercise the `api.permission:*` middleware without
     * having to recreate the whole role assignment.
     */
    private function revokeAdminPermission(string $permission): void
    {
        Role::query()->where('name', 'admin')->where('guard_name', 'api')->first()
            ->revokePermissionTo($permission);
    }

    public function test_unauthenticated_user_cannot_access_administrators_index(): void
    {
        $this->getJson('/api/v1/admin/administrators')
            ->assertUnauthorized();
    }

    public function test_regular_user_cannot_access_administrators_index(): void
    {
        $regular = User::factory()->create();
        $regular->assignRole('user');

        Passport::actingAs($regular, ['*'], 'api');

        $this->getJson('/api/v1/admin/administrators')
            ->assertForbidden();
    }

    public function test_admin_without_administrators_view_cannot_list_administrators(): void
    {
        $this->revokeAdminPermission('administrators.view');

        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        $this->getJson('/api/v1/admin/administrators')
            ->assertForbidden();
    }

    public function test_admin_without_administrators_view_cannot_load_options(): void
    {
        $this->revokeAdminPermission('administrators.view');

        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        $this->getJson('/api/v1/admin/administrators/options')
            ->assertForbidden();
    }

    public function test_admin_without_administrators_view_cannot_show_administrator(): void
    {
        $this->revokeAdminPermission('administrators.view');

        $target = $this->createAdminUser(['email' => 'target-admin@example.com']);
        $admin = $this->createAdminUser(['email' => 'caller-admin@example.com']);
        $this->actingAsAdmin($admin);

        $this->getJson('/api/v1/admin/administrators/'.$target->id)
            ->assertForbidden();
    }

    public function test_admin_with_administrators_view_can_list_administrators(): void
    {
        $this->createAdminUser(['email' => 'listed-admin@example.com']);
        $admin = $this->createAdminUser(['email' => 'viewer-admin@example.com']);
        $this->actingAsAdmin($admin);

        $this->getJson('/api/v1/admin/administrators')
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => ['items'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_admin_with_administrators_view_can_load_options(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        $this->getJson('/api/v1/admin/administrators/options')
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => ['roles', 'permissions'],
            ])
            ->assertJsonFragment(['admin'])
            ->assertJsonFragment(['administrators.view']);
    }

    public function test_admin_with_administrators_view_can_show_administrator(): void
    {
        $target = $this->createAdminUser(['email' => 'shown-admin@example.com']);
        $admin = $this->createAdminUser(['email' => 'caller-admin@example.com']);
        $this->actingAsAdmin($admin);

        $this->getJson('/api/v1/admin/administrators/'.$target->id)
            ->assertOk()
            ->assertJsonPath('data.item.email', 'shown-admin@example.com')
            ->assertJsonPath('data.item.roles.0', 'admin');
    }

    public function test_admin_without_administrators_create_cannot_store_administrator(): void
    {
        $this->revokeAdminPermission('administrators.create');

        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        $this->postJson('/api/v1/admin/administrators', [
            'name' => 'Denied Admin',
            'email' => 'denied-admin@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
            'roles' => ['admin'],
        ])->assertForbidden();
    }

    public function test_admin_with_administrators_create_can_store_administrator(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        $this->postJson('/api/v1/admin/administrators', [
            'name' => 'Managed Admin',
            'email' => 'managed-admin@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
            'roles' => ['admin'],
            'permissions' => ['administrators.view'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.item.email', 'managed-admin@example.com')
            ->assertJsonPath('data.item.roles.0', 'admin')
            ->assertJsonPath('data.item.direct_permissions.0', 'administrators.view');

        $this->assertDatabaseHas('users', [
            'email' => 'managed-admin@example.com',
        ]);
    }

    public function test_admin_without_administrators_update_cannot_update_administrator(): void
    {
        $this->revokeAdminPermission('administrators.update');

        $target = $this->createAdminUser(['email' => 'locked-admin@example.com']);
        $admin = $this->createAdminUser(['email' => 'caller-admin@example.com']);
        $this->actingAsAdmin($admin);

        $this->putJson('/api/v1/admin/administrators/'.$target->id, [
            'name' => 'Locked Admin',
            'email' => 'locked-admin@example.com',
            'password' => null,
            'password_confirmation' => null,
            'roles' => ['admin'],
        ])->assertForbidden();
    }

    public function test_admin_with_administrators_update_can_update_administrator(): void
    {
        $target = $this->createAdminUser(['email' => 'editable-admin@example.com']);
        $admin = $this->createAdminUser(['email' => 'caller-admin@example.com']);
        $this->actingAsAdmin($admin);

        $this->putJson('/api/v1/admin/administrators/'.$target->id, [
            'name' => 'Editable Admin Renamed',
            'email' => 'editable-admin@example.com',
            'password' => null,
            'password_confirmation' => null,
            'roles' => ['admin'],
            'permissions' => ['dashboard.view'],
        ])
            ->assertOk()
            ->assertJsonPath('data.item.name', 'Editable Admin Renamed')
            ->assertJsonPath('data.item.direct_permissions.0', 'dashboard.view');
    }

    public function test_admin_without_administrators_delete_cannot_destroy_administrator(): void
    {
        $this->revokeAdminPermission('administrators.delete');

        $target = $this->createAdminUser(['email' => 'protected-admin@example.com']);
        $admin = $this->createAdminUser(['email' => 'caller-admin@example.com']);
        $this->actingAsAdmin($admin);

        $this->deleteJson('/api/v1/admin/administrators/'.$target->id)
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_admin_with_administrators_delete_can_destroy_administrator(): void
    {
        $target = $this->createAdminUser(['email' => 'doomed-admin@example.com']);
        $admin = $this->createAdminUser(['email' => 'caller-admin@example.com']);
        $this->actingAsAdmin($admin);

        $this->deleteJson('/api/v1/admin/administrators/'.$target->id)
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }

    public function test_regular_user_cannot_access_any_administrator_route(): void
    {
        $regular = User::factory()->create();
        $regular->assignRole('user');
        $target = $this->createAdminUser(['email' => 'irrelevant-admin@example.com']);

        Passport::actingAs($regular, ['*'], 'api');

        $this->getJson('/api/v1/admin/administrators')->assertForbidden();
        $this->getJson('/api/v1/admin/administrators/options')->assertForbidden();
        $this->getJson('/api/v1/admin/administrators/'.$target->id)->assertForbidden();
        $this->postJson('/api/v1/admin/administrators', [
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
            'roles' => ['admin'],
        ])->assertForbidden();
        $this->putJson('/api/v1/admin/administrators/'.$target->id, [
            'name' => 'X',
            'email' => 'x@example.com',
            'password' => null,
            'password_confirmation' => null,
            'roles' => ['admin'],
        ])->assertForbidden();
        $this->deleteJson('/api/v1/admin/administrators/'.$target->id)->assertForbidden();
    }
}
