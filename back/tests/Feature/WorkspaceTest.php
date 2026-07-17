<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles exist
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_authenticated_user_can_list_their_workspaces(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $response = $this->getJson('/api/v1/workspaces');

        $response->assertOk()
            ->assertJsonFragment(['id' => $workspace->id]);
    }

    public function test_authenticated_user_can_create_a_workspace(): void
    {
        $user = User::factory()->create();
        $user->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']));
        $this->actingAsUser($user);

        $response = $this->postJson('/api/v1/workspaces', [
            'name' => 'Gastos Personales',
            'type' => 'personal',
            'currency_code' => 'MXN',
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Gastos Personales']);

        $this->assertDatabaseHas('workspaces', [
            'name' => 'Gastos Personales',
            'owner_id' => $user->id,
        ]);
    }

    public function test_workspace_creation_adds_owner_as_admin_member(): void
    {
        $user = User::factory()->create();
        $user->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']));
        $this->actingAsUser($user);

        $response = $this->postJson('/api/v1/workspaces', [
            'name' => 'Mi Workspace',
        ]);

        $response->assertCreated();
        $workspaceId = $response->json('data.id');

        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    public function test_user_can_view_workspace_they_belong_to(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->getJson("/api/v1/workspaces/{$workspace->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $workspace->id]);
    }

    public function test_user_cannot_view_workspace_they_do_not_belong_to(): void
    {
        $other = Workspace::factory()->create();
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->getJson("/api/v1/workspaces/{$other->id}")
            ->assertForbidden();
    }

    public function test_workspace_owner_can_update_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'Updated Name'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);
    }

    public function test_non_owner_non_admin_cannot_update_workspace(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();
        $viewer = User::factory()->create();
        $viewer->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']));
        $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
        $this->actingAsUser($viewer);

        $this->putJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_workspace_owner_can_delete_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('workspaces', ['id' => $workspace->id]);
    }

    public function test_guest_cannot_access_workspaces(): void
    {
        $this->getJson('/api/v1/workspaces')->assertUnauthorized();
    }
}
