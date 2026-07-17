<?php

namespace Tests\Feature;

use App\Events\UserPlanChanged;
use App\Models\Expense;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class SubscriptionPlanGatesTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    // -------------------------------------------------------------------------
    // 6.1 — free user cannot create second owned workspace
    // -------------------------------------------------------------------------

    public function test_free_user_cannot_create_second_owned_workspace(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $response = $this->postJson('/api/v1/workspaces', [
            'name' => 'Second Workspace',
            'type' => 'personal',
            'currency_code' => 'USD',
        ]);

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // 6.2 — premium user can create multiple workspaces
    // -------------------------------------------------------------------------

    public function test_premium_user_can_create_multiple_workspaces(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $user->assignRole('premium');
        $this->actingAsUser($user);

        $response = $this->postJson('/api/v1/workspaces', [
            'name' => 'Second Workspace',
            'type' => 'personal',
            'currency_code' => 'USD',
        ]);

        $response->assertCreated();
    }

    // -------------------------------------------------------------------------
    // 6.3 — member count excludes foreign workspaces
    // -------------------------------------------------------------------------

    public function test_member_count_excludes_foreign_workspaces(): void
    {
        $otherOwner = User::factory()->create();
        $otherOwner->assignRole('user');
        $foreignWorkspace = Workspace::factory()->create(['owner_id' => $otherOwner->id]);

        $user = User::factory()->create();
        $user->assignRole('user');
        // User is a member of a foreign workspace but owns no workspaces
        $foreignWorkspace->members()->attach($user->id, ['role' => 'editor']);
        $this->actingAsUser($user);

        // User has 0 owned workspaces, so they can create one
        $response = $this->postJson('/api/v1/workspaces', [
            'name' => 'My First Workspace',
            'type' => 'personal',
            'currency_code' => 'USD',
        ]);

        $response->assertCreated();
    }

    // -------------------------------------------------------------------------
    // 6.4 — free owner cannot invite members
    // -------------------------------------------------------------------------

    public function test_free_owner_cannot_invite_members(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $newUser = User::factory()->create();
        $this->actingAsUser($owner);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/members", [
            'email' => $newUser->email,
            'role' => 'editor',
        ]);

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // 6.5 — member blocked from creating expense in free owner workspace
    // -------------------------------------------------------------------------

    public function test_member_blocked_from_creating_expense_in_free_owner_workspace(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace();

        $member = User::factory()->create();
        $member->assignRole('user');
        $workspace->members()->attach($member->id, ['role' => 'editor']);
        $this->actingAsUser($member);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'description' => 'Test expense',
            'amount' => 100,
            'currency_code' => 'USD',
            'date' => '2024-01-15',
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $response->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // 6.6 — owner retains access after downgrade
    // -------------------------------------------------------------------------

    public function test_owner_retains_access_after_downgrade(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');
        $owner->removeRole('premium'); // downgrade
        $this->actingAsUser($owner);

        // Owner can still read their workspace data
        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}");
        $response->assertOk();

        // Owner can still create expenses in their own workspace
        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'description' => 'Owner expense after downgrade',
            'amount' => 50,
            'currency_code' => 'USD',
            'date' => '2024-01-15',
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $response->assertCreated();
    }

    // -------------------------------------------------------------------------
    // 6.7 — data preserved on owner downgrade
    // -------------------------------------------------------------------------

    public function test_data_preserved_on_owner_downgrade(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $member = User::factory()->create();
        $member->assignRole('user');
        $workspace->members()->attach($member->id, ['role' => 'editor']);

        // Downgrade owner
        $owner->assignRole('premium');
        $owner->removeRole('premium');

        // Members still exist in pivot table
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $member->id,
        ]);

        // Workspace still exists
        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);
    }

    // -------------------------------------------------------------------------
    // 6.8 — admin can assign premium plan
    // -------------------------------------------------------------------------

    public function test_admin_can_assign_premium_plan(): void
    {
        Event::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAsUser($admin);

        $targetUser = User::factory()->create();
        $targetUser->assignRole('user');

        $response = $this->postJson("/api/v1/admin/users/{$targetUser->id}/plan", [
            'plan' => 'premium',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.plan', 'premium');

        $this->assertTrue($targetUser->fresh()->hasRole('premium'));
        Event::assertDispatched(UserPlanChanged::class, function (UserPlanChanged $event) use ($targetUser): bool {
            return $event->user->id === $targetUser->id && $event->plan === 'premium';
        });
    }

    // -------------------------------------------------------------------------
    // 6.9 — admin can revoke premium plan
    // -------------------------------------------------------------------------

    public function test_admin_can_revoke_premium_plan(): void
    {
        Event::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAsUser($admin);

        $targetUser = User::factory()->create();
        $targetUser->assignRole('user');
        $targetUser->assignRole('premium');

        $response = $this->postJson("/api/v1/admin/users/{$targetUser->id}/plan", [
            'plan' => 'free',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.plan', 'free');

        $this->assertFalse($targetUser->fresh()->hasRole('premium'));
        Event::assertDispatched(UserPlanChanged::class, function (UserPlanChanged $event) use ($targetUser): bool {
            return $event->user->id === $targetUser->id && $event->plan === 'free';
        });
    }

    // -------------------------------------------------------------------------
    // 6.10 — UserResource exposes plan field
    // -------------------------------------------------------------------------

    public function test_user_resource_exposes_plan_field(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.plan', 'free');

        // Assign premium and check again
        $user->assignRole('premium');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $response = $this->getJson('/api/v1/auth/me');
        $response->assertOk()
            ->assertJsonPath('user.plan', 'premium');
    }
}
