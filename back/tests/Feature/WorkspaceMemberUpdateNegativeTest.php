<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class WorkspaceMemberUpdateNegativeTest extends TestCase
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

    public function test_updating_non_member_returns_workspace_member_not_found(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');
        $this->actingAsUser($owner);

        $outsider = User::factory()->create();

        $this->putJson("/api/v1/workspaces/{$workspace->id}/members/{$outsider->id}", [
            'role' => 'guest',
        ])
            ->assertNotFound()
            ->assertJsonPath('status', 404)
            ->assertJsonPath('code', 'workspace_member_not_found');
    }

    public function test_viewer_member_cannot_update_other_member_role(): void
    {
        // H-013 fix: the FormRequest now runs the `manageMembers` policy
        // gate BEFORE the input rules, so any unauthorized actor (viewer,
        // editor, non-premium owner) gets 403 — even if they send a value
        // that the FormRequest would otherwise reject with 422.
        ['workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner = $workspace->owner;
        $owner->assignRole('premium');

        $viewer = User::factory()->create();
        $viewer->assignRole('user');
        $workspace->members()->attach($viewer->id, ['role' => 'viewer']);

        $otherMember = User::factory()->create();
        $workspace->members()->attach($otherMember->id, ['role' => 'guest']);

        $this->actingAsUser($viewer);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/members/{$otherMember->id}", [
            'role' => 'guest',
        ])->assertForbidden();
    }

    public function test_viewer_member_with_non_whitelisted_role_still_gets_403_not_422(): void
    {
        // H-013 fix: a viewer sending `role: 'editor'` (not in the
        // whitelist) must still get 403, not 422. Authorization must run
        // before validation.
        ['workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner = $workspace->owner;
        $owner->assignRole('premium');

        $viewer = User::factory()->create();
        $viewer->assignRole('user');
        $workspace->members()->attach($viewer->id, ['role' => 'viewer']);

        $otherMember = User::factory()->create();
        $workspace->members()->attach($otherMember->id, ['role' => 'guest']);

        $this->actingAsUser($viewer);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/members/{$otherMember->id}", [
            'role' => 'editor',
        ])->assertForbidden();
    }

    public function test_editor_member_cannot_update_other_member_role(): void
    {
        // Same as viewer: editors don't have `manageMembers` either. After
        // H-013, the policy check runs first, so any role value produces a
        // 403 rather than a 422.
        ['workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner = $workspace->owner;
        $owner->assignRole('premium');

        $editor = User::factory()->create();
        $editor->assignRole('user');
        $workspace->members()->attach($editor->id, ['role' => 'editor']);

        $otherMember = User::factory()->create();
        $workspace->members()->attach($otherMember->id, ['role' => 'guest']);

        $this->actingAsUser($editor);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/members/{$otherMember->id}", [
            'role' => 'guest',
        ])->assertForbidden();
    }

    public function test_updating_owners_role_to_guest_returns_422_cannot_change_owner_role(): void
    {
        // H-012 fix: the owner can NEVER be demoted via this endpoint.
        // Demoting them would break the `WorkspacePolicy::manageMembers`
        // invariant (it reads the role from the pivot for some consumers).
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');
        $this->actingAsUser($owner);

        $response = $this->putJson("/api/v1/workspaces/{$workspace->id}/members/{$owner->id}", [
            'role' => 'guest',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'cannot_change_owner_role');

        // Owner's role in the pivot must remain `owner`.
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
        $this->assertDatabaseMissing('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'guest',
        ]);
    }

    public function test_updating_owners_role_to_owner_is_accepted(): void
    {
        // Companion to H-012: setting the owner's role to `owner` (or
        // omitting the `role` key) must NOT be blocked — only the demotion
        // path is forbidden.
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');
        $this->actingAsUser($owner);

        $response = $this->putJson("/api/v1/workspaces/{$workspace->id}/members/{$owner->id}", [
            'role' => 'owner',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'role' => 'owner',
        ]);
    }
}
