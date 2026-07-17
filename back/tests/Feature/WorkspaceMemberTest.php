<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class WorkspaceMemberTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'premium', 'guard_name' => 'api']);
    }

    public function test_owner_can_list_members(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($owner);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/members");

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'role']]]);
    }

    public function test_owner_can_add_a_member(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');
        $newUser = User::factory()->create();
        $this->actingAsUser($owner);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/members", [
            'email' => $newUser->email,
            'role' => 'guest',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', $newUser->email)
            ->assertJsonPath('data.role', 'guest');

        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $newUser->id,
            'role' => 'guest',
        ]);
    }

    public function test_owner_can_update_member_role(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');
        $member = User::factory()->create();
        $workspace->members()->attach($member->id, ['role' => 'viewer']);
        $this->actingAsUser($owner);

        $response = $this->putJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}", [
            'role' => 'guest',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.role', 'guest');

        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
            'role' => 'guest',
        ]);
    }

    public function test_owner_can_remove_a_member(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');
        $member = User::factory()->create();
        $workspace->members()->attach($member->id, ['role' => 'viewer']);
        $this->actingAsUser($owner);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
        ]);
    }

    public function test_editor_cannot_add_members(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();
        $editor = User::factory()->create();
        $workspace->members()->attach($editor->id, ['role' => 'editor']);
        $newUser = User::factory()->create();
        $this->actingAsUser($editor);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/members", [
            'email' => $newUser->email,
            'role' => 'viewer',
        ])->assertForbidden();
    }

    public function test_viewer_cannot_add_members(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();
        $viewer = User::factory()->create();
        $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
        $newUser = User::factory()->create();
        $this->actingAsUser($viewer);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/members", [
            'email' => $newUser->email,
            'role' => 'viewer',
        ])->assertForbidden();
    }

    public function test_cannot_add_a_user_who_is_already_a_member(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');
        $this->actingAsUser($owner);

        // Owner is already a member — try to add them again
        $this->postJson("/api/v1/workspaces/{$workspace->id}/members", [
            'email' => $owner->email,
            'role' => 'editor',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'workspace_member_already_exists')
            ->assertJsonPath('message', 'User is already a member of this workspace.')
            ->assertJsonMissingPath('fieldErrors');
    }

    public function test_cannot_remove_the_owner(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');
        $this->actingAsUser($owner);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$owner->id}")
            ->assertUnprocessable()
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'workspace_owner_cannot_be_removed')
            ->assertJsonPath('message', 'Cannot remove the workspace owner.')
            ->assertJsonMissingPath('fieldErrors');
    }

    public function test_returns_404_when_adding_nonexistent_email(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');
        $this->actingAsUser($owner);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/members", [
            'email' => 'nobody@example.com',
            'role' => 'viewer',
        ])
            ->assertNotFound()
            ->assertJsonPath('status', 404)
            ->assertJsonPath('code', 'user_not_found')
            ->assertJsonPath('message', 'User not found.');
    }

    public function test_returns_workspace_member_not_found_when_updating_non_member(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');
        $outsider = User::factory()->create();
        $this->actingAsUser($owner);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/members/{$outsider->id}", [
            'role' => 'guest',
        ])
            ->assertNotFound()
            ->assertJsonPath('status', 404)
            ->assertJsonPath('code', 'workspace_member_not_found')
            ->assertJsonPath('message', 'Workspace member not found.');
    }

    public function test_returns_workspace_member_not_found_when_removing_missing_member(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');
        $this->actingAsUser($owner);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/members/999999")
            ->assertNotFound()
            ->assertJsonPath('status', 404)
            ->assertJsonPath('code', 'workspace_member_not_found')
            ->assertJsonPath('message', 'Workspace member not found.');
    }

    public function test_unauthenticated_user_cannot_access_members(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();

        $this->getJson("/api/v1/workspaces/{$workspace->id}/members")
            ->assertUnauthorized();
    }

    public function test_non_member_cannot_list_members(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();
        $outsider = User::factory()->create();
        $this->actingAsUser($outsider);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/members")
            ->assertForbidden();
    }
}
