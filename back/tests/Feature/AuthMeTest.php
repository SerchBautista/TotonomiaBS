<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesAdmin;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class AuthMeTest extends TestCase
{
    use CreatesAdmin;
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    public function test_authenticated_user_receives_full_profile_via_user_resource(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $user->default_workspace_id = $user->ownedWorkspaces()->first()?->id;
        $user->two_factor_enabled = false;
        $user->save();

        $this->actingAsUser($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'plan',
                    'default_workspace_id',
                    'theme',
                    'locale',
                    'timezone',
                    'two_factor_enabled',
                    'permissions',
                ],
            ])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.role', 'user')
            ->assertJsonPath('user.plan', 'free')
            ->assertJsonPath('user.two_factor_enabled', false);
    }

    public function test_admin_receives_all_permissions_in_me_endpoint(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'user' => [
                    'permissions',
                ],
            ])
            ->assertJsonCount(11, 'user.permissions')
            ->assertJsonPath('user.role', 'admin');

        $permissions = $response->json('user.permissions');
        $this->assertIsArray($permissions);
        $this->assertContains('users.view', $permissions);
        $this->assertContains('users.assign-plan', $permissions);
        $this->assertContains('administrators.view', $permissions);
        $this->assertContains('administrators.create', $permissions);
        $this->assertContains('profile.view', $permissions);
    }

    public function test_regular_user_receives_user_permissions_in_me_endpoint(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'user' => [
                    'permissions',
                ],
            ]);

        $permissions = $response->json('user.permissions');
        $this->assertIsArray($permissions);
        $this->assertContains('profile.view', $permissions);
        $this->assertContains('profile.update', $permissions);
        $this->assertContains('two-factor.update', $permissions);
        $this->assertContains('files.upload', $permissions);
        $this->assertNotContains('users.view', $permissions);
        $this->assertNotContains('users.assign-plan', $permissions);
    }

    public function test_user_without_roles_receives_empty_permissions_array(): void
    {
        $user = User::factory()->create();
        $this->actingAsUser($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'user' => [
                    'permissions',
                ],
            ]);

        $permissions = $response->json('user.permissions');
        $this->assertIsArray($permissions);
        $this->assertEmpty($permissions);
    }

    public function test_permissions_field_is_always_an_array(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk();
        $permissions = $response->json('user.permissions');
        $this->assertIsArray($permissions);

        foreach ($permissions as $permission) {
            $this->assertIsString($permission);
        }
    }

    public function test_unauthenticated_user_cannot_access_me(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }
}
