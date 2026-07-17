<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class UserDefaultWorkspaceTest extends TestCase
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

    public function test_user_can_set_default_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $response = $this->putJson('/api/v1/user/default-workspace', [
            'workspace_id' => $workspace->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.default_workspace_id', $workspace->id);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'default_workspace_id' => $workspace->id,
        ]);
    }

    public function test_cannot_set_default_workspace_user_is_not_member_of(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $otherWorkspace = Workspace::factory()->create();
        $this->actingAsUser($user);

        $response = $this->putJson('/api/v1/user/default-workspace', [
            'workspace_id' => $otherWorkspace->id,
        ]);

        $response->assertUnprocessable();

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
            'default_workspace_id' => $otherWorkspace->id,
        ]);
    }

    public function test_cannot_set_default_workspace_with_nonexistent_id(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $response = $this->putJson('/api/v1/user/default-workspace', [
            'workspace_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        $response->assertUnprocessable();
    }

    public function test_auth_me_includes_default_workspace_id(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $user->update(['default_workspace_id' => $workspace->id]);
        $this->actingAsUser($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.default_workspace_id', $workspace->id);
    }

    public function test_default_workspace_id_is_null_in_response_when_not_set(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.default_workspace_id', null);
    }

    public function test_removing_member_clears_default_workspace_id(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');
        $member = User::factory()->create();
        $member->assignRole('user');
        $workspace->members()->attach($member->id, ['role' => 'viewer']);
        $member->update(['default_workspace_id' => $workspace->id]);
        $this->actingAsUser($owner);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'default_workspace_id' => null,
        ]);
    }

    public function test_removing_member_from_non_default_workspace_does_not_affect_default(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');
        $member = User::factory()->create();
        $member->assignRole('user');
        $workspace->members()->attach($member->id, ['role' => 'viewer']);

        // Member's default is a different workspace
        $otherWorkspace = Workspace::factory()->create(['owner_id' => $member->id]);
        $otherWorkspace->members()->attach($member->id, ['role' => 'admin']);
        $member->update(['default_workspace_id' => $otherWorkspace->id]);

        $this->actingAsUser($owner);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('users', [
            'id' => $member->id,
            'default_workspace_id' => $otherWorkspace->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_set_default_workspace(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();

        $this->putJson('/api/v1/user/default-workspace', [
            'workspace_id' => $workspace->id,
        ])->assertUnauthorized();
    }
}
