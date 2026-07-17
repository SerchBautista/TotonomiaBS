<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Models\Workspace;
use App\Policies\WorkspacePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspacePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'premium', 'guard_name' => 'api']);
    }

    public function test_view_any_returns_true_for_any_authenticated_user(): void
    {
        $user = User::factory()->create();

        $policy = new WorkspacePolicy;

        $this->assertTrue($policy->viewAny($user));
    }

    public function test_owner_can_view_their_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        $policy = new WorkspacePolicy;

        $this->assertTrue($policy->view($owner, $workspace));
    }

    public function test_member_can_view_workspace(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);
        $workspace->members()->attach($member->id, ['role' => 'member']);

        $policy = new WorkspacePolicy;

        $this->assertTrue($policy->view($member, $workspace));
    }

    public function test_non_member_cannot_view_workspace(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        $policy = new WorkspacePolicy;

        $this->assertFalse($policy->view($outsider, $workspace));
    }

    public function test_premium_user_can_create_workspace(): void
    {
        $user = User::factory()->create();
        $user->assignRole('premium');

        $policy = new WorkspacePolicy;

        $this->assertTrue($policy->create($user));
    }

    public function test_free_user_can_create_first_workspace(): void
    {
        $user = User::factory()->create();

        $policy = new WorkspacePolicy;

        $this->assertTrue($policy->create($user));
    }

    public function test_free_user_cannot_create_second_workspace(): void
    {
        $user = User::factory()->create();
        // User already owns one workspace.
        Workspace::factory()->create(['owner_id' => $user->id]);

        $policy = new WorkspacePolicy;

        $this->assertFalse($policy->create($user));
    }

    public function test_owner_can_update_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $policy = new WorkspacePolicy;

        $this->assertTrue($policy->update($owner, $workspace));
    }

    public function test_admin_member_cannot_update_workspace(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($admin->id, ['role' => 'admin']);

        $policy = new WorkspacePolicy;

        $this->assertFalse($policy->update($admin, $workspace));
    }

    public function test_non_member_cannot_update_workspace(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $policy = new WorkspacePolicy;

        $this->assertFalse($policy->update($outsider, $workspace));
    }

    public function test_owner_can_delete_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $policy = new WorkspacePolicy;

        $this->assertTrue($policy->delete($owner, $workspace));
    }

    public function test_non_owner_cannot_delete_workspace(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($admin->id, ['role' => 'admin']);

        $policy = new WorkspacePolicy;

        $this->assertFalse($policy->delete($admin, $workspace));
    }

    public function test_owner_can_manage_members_when_premium(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('premium');
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $policy = new WorkspacePolicy;

        $this->assertTrue($policy->manageMembers($owner, $workspace));
    }

    public function test_owner_cannot_manage_members_when_not_premium(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $policy = new WorkspacePolicy;

        $this->assertFalse($policy->manageMembers($owner, $workspace));
    }

    public function test_admin_member_cannot_manage_members(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('premium');
        $admin = User::factory()->create();
        $admin->assignRole('premium');
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($admin->id, ['role' => 'admin']);

        $policy = new WorkspacePolicy;

        $this->assertFalse($policy->manageMembers($admin, $workspace));
    }

    public function test_viewer_cannot_manage_members(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('premium');
        $viewer = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($viewer->id, ['role' => 'viewer']);

        $policy = new WorkspacePolicy;

        $this->assertFalse($policy->manageMembers($viewer, $workspace));
    }

    public function test_guest_cannot_manage_members(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('premium');
        $guest = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($guest->id, ['role' => 'guest']);

        $policy = new WorkspacePolicy;

        $this->assertFalse($policy->manageMembers($guest, $workspace));
    }

    public function test_non_member_cannot_manage_members(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('premium');
        $outsider = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $policy = new WorkspacePolicy;

        $this->assertFalse($policy->manageMembers($outsider, $workspace));
    }
}
