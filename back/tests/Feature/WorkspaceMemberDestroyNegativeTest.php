<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class WorkspaceMemberDestroyNegativeTest extends TestCase
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

    public function test_destroying_workspace_owner_returns_422(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');
        $this->actingAsUser($owner);

        $response = $this->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$owner->id}");

        $response->assertUnprocessable()
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'workspace_owner_cannot_be_removed')
            ->assertJsonPath('message', 'Cannot remove the workspace owner.')
            ->assertJsonMissingPath('fieldErrors');

        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_destroying_non_member_returns_workspace_member_not_found(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');
        $this->actingAsUser($owner);

        $outsider = User::factory()->create();

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$outsider->id}")
            ->assertNotFound()
            ->assertJsonPath('status', 404)
            ->assertJsonPath('code', 'workspace_member_not_found');
    }

    public function test_non_member_cannot_delete_workspace_member(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');

        $member = User::factory()->create();
        $member->assignRole('user');
        $workspace->members()->attach($member->id, ['role' => 'guest']);

        $outsider = User::factory()->create();
        $outsider->assignRole('user');
        $this->actingAsUser($outsider);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}")
            ->assertForbidden();
    }
}
