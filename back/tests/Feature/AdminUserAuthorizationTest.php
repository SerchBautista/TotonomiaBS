<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;
use Tests\CreatesAdmin;
use Tests\TestCase;

class AdminUserAuthorizationTest extends TestCase
{
    use CreatesAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    public function test_unauthenticated_user_cannot_access_admin_users_index(): void
    {
        $this->getJson('/api/v1/admin/users')
            ->assertUnauthorized();
    }

    public function test_regular_user_cannot_access_admin_users_index(): void
    {
        $regular = User::factory()->create();
        $regular->assignRole('user');

        Passport::actingAs($regular, ['*'], 'api');

        $this->getJson('/api/v1/admin/users')
            ->assertForbidden();
    }

    public function test_admin_without_users_view_cannot_list_admin_users(): void
    {
        Role::query()->where('name', 'admin')->where('guard_name', 'api')->first()
            ->revokePermissionTo('users.view');

        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        $this->getJson('/api/v1/admin/users')
            ->assertForbidden();
    }

    public function test_admin_without_users_view_cannot_show_admin_user(): void
    {
        Role::query()->where('name', 'admin')->where('guard_name', 'api')->first()
            ->revokePermissionTo('users.view');

        $target = User::factory()->create();
        $target->assignRole('user');
        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        $this->getJson('/api/v1/admin/users/'.$target->id)
            ->assertForbidden();
    }

    public function test_admin_with_users_view_can_list_admin_users(): void
    {
        $target = User::factory()->create(['email' => 'listed-user@example.com']);
        $target->assignRole('user');

        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        $response = $this->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => ['items'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $emails = collect($response->json('data.items'))->pluck('email');
        $this->assertTrue($emails->contains('listed-user@example.com'));
    }

    public function test_admin_with_users_view_can_show_admin_user(): void
    {
        $target = User::factory()->create(['email' => 'shown-user@example.com']);
        $target->assignRole('user');

        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        $this->getJson('/api/v1/admin/users/'.$target->id)
            ->assertOk()
            ->assertJsonPath('data.item.email', 'shown-user@example.com')
            ->assertJsonPath('data.item.plan', 'free');
    }

    public function test_regular_user_cannot_show_admin_user(): void
    {
        $target = User::factory()->create();
        $target->assignRole('user');
        $regular = User::factory()->create();
        $regular->assignRole('user');

        Passport::actingAs($regular, ['*'], 'api');

        $this->getJson('/api/v1/admin/users/'.$target->id)
            ->assertForbidden();
    }
}
