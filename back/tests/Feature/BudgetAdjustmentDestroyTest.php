<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetAdjustment;
use App\Models\Category;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class BudgetAdjustmentDestroyTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_owner_can_delete_adjustment_and_effective_budget_recomputes(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $fromCategory] = $this->createUserWithWorkspace();
        $toCategory = Category::factory()->forUser($owner)->create();
        $toCategory->workspaces()->attach($workspace->id);

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $fromCategory->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $month = Carbon::now()->startOfMonth();

        $adjustment = BudgetAdjustment::create([
            'workspace_id' => $workspace->id,
            'month' => $month,
            'from_category_id' => $fromCategory->id,
            'to_category_id' => $toCategory->id,
            'amount' => 150,
            'reason' => 'Rebalance',
            'user_id' => $owner->id,
        ]);

        $this->actingAsUser($owner);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments/{$adjustment->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('budget_adjustments', ['id' => $adjustment->id]);

        // After deletion, the effective budget for the from category must
        // return to the base amount (500) — the 150 outbound adjustment is gone.
        $effective = app(\App\Contracts\CalculateEffectiveBudgetActionInterface::class)
            ->execute($workspace, $fromCategory->id, $month);

        $this->assertEquals(500.0, $effective['effective_budget']);
        $this->assertEquals(0.0, $effective['adjustments_out']);

        // And the to_category also loses the inbound adjustment.
        $toEffective = app(\App\Contracts\CalculateEffectiveBudgetActionInterface::class)
            ->execute($workspace, $toCategory->id, $month);

        $this->assertEquals(0.0, $toEffective['adjustments_in']);
    }

    public function test_non_member_cannot_delete_adjustment(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $fromCategory] = $this->createUserWithWorkspace();
        $toCategory = Category::factory()->forUser($owner)->create();
        $toCategory->workspaces()->attach($workspace->id);

        $adjustment = BudgetAdjustment::create([
            'workspace_id' => $workspace->id,
            'month' => Carbon::now()->startOfMonth(),
            'from_category_id' => $fromCategory->id,
            'to_category_id' => $toCategory->id,
            'amount' => 50,
            'user_id' => $owner->id,
        ]);

        $outsider = User::factory()->create();
        $outsider->assignRole('user');
        $this->actingAsUser($outsider);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments/{$adjustment->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('budget_adjustments', ['id' => $adjustment->id]);
    }

    public function test_viewer_cannot_delete_adjustment(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $fromCategory] = $this->createUserWithWorkspace();
        $toCategory = Category::factory()->forUser($owner)->create();
        $toCategory->workspaces()->attach($workspace->id);

        $adjustment = BudgetAdjustment::create([
            'workspace_id' => $workspace->id,
            'month' => Carbon::now()->startOfMonth(),
            'from_category_id' => $fromCategory->id,
            'to_category_id' => $toCategory->id,
            'amount' => 50,
            'user_id' => $owner->id,
        ]);

        $viewer = User::factory()->create();
        $viewer->assignRole('user');
        $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
        $this->actingAsUser($viewer);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments/{$adjustment->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('budget_adjustments', ['id' => $adjustment->id]);
    }

    public function test_editor_cannot_delete_adjustment(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $fromCategory] = $this->createUserWithWorkspace();
        $toCategory = Category::factory()->forUser($owner)->create();
        $toCategory->workspaces()->attach($workspace->id);

        $adjustment = BudgetAdjustment::create([
            'workspace_id' => $workspace->id,
            'month' => Carbon::now()->startOfMonth(),
            'from_category_id' => $fromCategory->id,
            'to_category_id' => $toCategory->id,
            'amount' => 50,
            'user_id' => $owner->id,
        ]);

        $editor = User::factory()->create();
        $editor->assignRole('user');
        $workspace->members()->attach($editor->id, ['role' => 'editor']);
        $this->actingAsUser($editor);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments/{$adjustment->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('budget_adjustments', ['id' => $adjustment->id]);
    }

    public function test_deleting_unknown_adjustment_returns_404(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($owner);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments/00000000-0000-0000-0000-000000000000")
            ->assertNotFound();
    }
}
