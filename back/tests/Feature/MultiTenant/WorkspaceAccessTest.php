<?php

namespace Tests\Feature\MultiTenant;

use App\Models\Expense;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class WorkspaceAccessTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_user_cannot_access_expenses_from_another_workspace(): void
    {
        // User B's workspace (user A has no membership)
        ['user' => $userB, 'workspace' => $workspaceB] = $this->createUserWithWorkspace();

        // User A
        $userA = User::factory()->create();
        $this->actingAsUser($userA);

        $this->getJson("/api/v1/workspaces/{$workspaceB->id}/expenses")
            ->assertForbidden();
    }

    public function test_user_cannot_view_workspace_they_are_not_a_member_of(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();
        $outsider = User::factory()->create();
        $this->actingAsUser($outsider);

        $this->getJson("/api/v1/workspaces/{$workspace->id}")
            ->assertForbidden();
    }

    public function test_member_with_viewer_role_can_read_but_not_write(): void
    {
        ['workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();
        $viewer = User::factory()->create();
        $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
        $this->actingAsUser($viewer);

        // Can read
        $this->getJson("/api/v1/workspaces/{$workspace->id}/expenses")->assertOk();

        // Cannot write
        $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
            'date' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_workspace_policy_prevents_idor_on_expense(): void
    {
        // Expense belongs to workspace A
        ['user' => $userA, 'workspace' => $workspaceA, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();
        $expense = Expense::factory()->create([
            'workspace_id' => $workspaceA->id,
            'user_id' => $userA->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        // User B tries to access it via their own (non-existent) workspace route
        ['user' => $userB, 'workspace' => $workspaceB] = $this->createUserWithWorkspace();
        $this->actingAsUser($userB);

        $this->getJson("/api/v1/workspaces/{$workspaceB->id}/expenses/{$expense->id}")
            ->assertForbidden();
    }
}
