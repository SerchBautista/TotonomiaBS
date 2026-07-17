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

class BudgetAdjustmentStoreTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_owner_creates_adjustment_and_effective_budget_decreases(): void
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
        $this->actingAsUser($owner);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments", [
            'from_category_id' => $fromCategory->id,
            'to_category_id' => $toCategory->id,
            'amount' => 100,
            'month' => $month->format('Y-m'),
            'reason' => 'Rebalance',
        ])->assertCreated();

        $this->assertDatabaseHas('budget_adjustments', [
            'workspace_id' => $workspace->id,
            'from_category_id' => $fromCategory->id,
            'to_category_id' => $toCategory->id,
            'user_id' => $owner->id,
            'amount' => 100,
            'reason' => 'Rebalance',
        ]);

        $this->assertEquals(1, BudgetAdjustment::count());

        // The effective budget for the from category must reflect the outbound
        // adjustment (500 base - 100 out = 400).
        $effective = app(\App\Contracts\CalculateEffectiveBudgetActionInterface::class)
            ->execute($workspace, $fromCategory->id, $month);

        $this->assertEquals(400.0, $effective['effective_budget']);
        $this->assertEquals(0.0, $effective['adjustments_in']);
        $this->assertEquals(100.0, $effective['adjustments_out']);

        $response->assertJsonPath('data.from_category_id', $fromCategory->id);
        $response->assertJsonPath('data.to_category_id', $toCategory->id);
        $response->assertJsonPath('data.amount', '100.00');
    }

    public function test_adjustment_greater_than_available_returns_422_with_insufficient_funds_code(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $fromCategory] = $this->createUserWithWorkspace();
        $toCategory = Category::factory()->forUser($owner)->create();
        $toCategory->workspaces()->attach($workspace->id);

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $fromCategory->id,
            'amount' => '50.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $this->actingAsUser($owner);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments", [
            'from_category_id' => $fromCategory->id,
            'to_category_id' => $toCategory->id,
            'amount' => 999,
            'month' => Carbon::now()->format('Y-m'),
        ])->assertUnprocessable();

        $this->assertEquals('budget_adjustment_insufficient_funds', $response->json('code'));
        $this->assertEquals('Insufficient funds in the selected category.', $response->json('message'));
        $this->assertArrayHasKey('meta', $response->json());
        $this->assertArrayHasKey('suggested_categories', $response->json('meta'));
        $this->assertDatabaseCount('budget_adjustments', 0);
    }

    public function test_adjustment_with_zero_amount_returns_422(): void
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

        $this->actingAsUser($owner);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments", [
            'from_category_id' => $fromCategory->id,
            'to_category_id' => $toCategory->id,
            'amount' => 0,
            'month' => Carbon::now()->format('Y-m'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount'], 'fieldErrors');

        $this->assertDatabaseCount('budget_adjustments', 0);
    }

    public function test_adjustment_with_negative_amount_returns_422(): void
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

        $this->actingAsUser($owner);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments", [
            'from_category_id' => $fromCategory->id,
            'to_category_id' => $toCategory->id,
            'amount' => -50,
            'month' => Carbon::now()->format('Y-m'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount'], 'fieldErrors');

        $this->assertDatabaseCount('budget_adjustments', 0);
    }

    public function test_adjustment_without_from_category_returns_422(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $fromCategory] = $this->createUserWithWorkspace();
        $toCategory = Category::factory()->forUser($owner)->create();
        $toCategory->workspaces()->attach($workspace->id);

        $this->actingAsUser($owner);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments", [
            'to_category_id' => $toCategory->id,
            'amount' => 50,
            'month' => Carbon::now()->format('Y-m'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from_category_id'], 'fieldErrors');
    }

    public function test_adjustment_without_amount_returns_422(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $fromCategory] = $this->createUserWithWorkspace();
        $toCategory = Category::factory()->forUser($owner)->create();
        $toCategory->workspaces()->attach($workspace->id);

        $this->actingAsUser($owner);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments", [
            'from_category_id' => $fromCategory->id,
            'to_category_id' => $toCategory->id,
            'month' => Carbon::now()->format('Y-m'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount'], 'fieldErrors');
    }

    public function test_adjustment_without_to_category_returns_422(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $fromCategory] = $this->createUserWithWorkspace();

        $this->actingAsUser($owner);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments", [
            'from_category_id' => $fromCategory->id,
            'amount' => 50,
            'month' => Carbon::now()->format('Y-m'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to_category_id'], 'fieldErrors');
    }

    public function test_adjustment_with_invalid_month_format_returns_422(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $fromCategory] = $this->createUserWithWorkspace();
        $toCategory = Category::factory()->forUser($owner)->create();
        $toCategory->workspaces()->attach($workspace->id);

        $this->actingAsUser($owner);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments", [
            'from_category_id' => $fromCategory->id,
            'to_category_id' => $toCategory->id,
            'amount' => 50,
            'month' => '2026-13-01',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['month'], 'fieldErrors');
    }

    public function test_adjustment_with_same_from_and_to_category_returns_422(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $fromCategory] = $this->createUserWithWorkspace();

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $fromCategory->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $this->actingAsUser($owner);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments", [
            'from_category_id' => $fromCategory->id,
            'to_category_id' => $fromCategory->id,
            'amount' => 50,
            'month' => Carbon::now()->format('Y-m'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to_category_id'], 'fieldErrors');
    }

    public function test_adjustment_with_category_from_other_workspace_returns_422(): void
    {
        // Create the workspace + categories for the owner
        ['user' => $owner, 'workspace' => $workspace, 'category' => $fromCategory] = $this->createUserWithWorkspace();
        $toCategory = Category::factory()->forUser($owner)->create();
        $toCategory->workspaces()->attach($workspace->id);

        // Create a foreign category that belongs to a different owner
        $foreignOwner = User::factory()->create();
        $foreignOwner->assignRole('user');
        $foreignCategory = Category::factory()->forUser($foreignOwner)->create();

        $this->actingAsUser($owner);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments", [
            'from_category_id' => $foreignCategory->id,
            'to_category_id' => $toCategory->id,
            'amount' => 50,
            'month' => Carbon::now()->format('Y-m'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from_category_id'], 'fieldErrors');
    }

    public function test_non_member_cannot_create_adjustment(): void
    {
        // Setup: a workspace owned by user A; a budget that would otherwise
        // allow a valid adjustment.
        ['workspace' => $workspace, 'category' => $fromCategory] = $this->createUserWithWorkspace();
        $owner = $workspace->owner;
        $toCategory = Category::factory()->forUser($owner)->create();
        $toCategory->workspaces()->attach($workspace->id);

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $fromCategory->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $outsider = User::factory()->create();
        $outsider->assignRole('user');
        $this->actingAsUser($outsider);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments", [
            'from_category_id' => $fromCategory->id,
            'to_category_id' => $toCategory->id,
            'amount' => 50,
            'month' => Carbon::now()->format('Y-m'),
        ])->assertForbidden();

        $this->assertDatabaseCount('budget_adjustments', 0);
    }

    /**
     * H-005 regression: a non-member must always be rejected with 403,
     * even when the payload is otherwise valid and would only fail the
     * domain "insufficient funds" check. Otherwise the 422 leaks the
     * existence of a budget (or its size) to outsiders.
     */
    public function test_non_member_gets_403_even_with_valid_data_and_insufficient_funds(): void
    {
        // Setup: a workspace owned by user A with a small budget and a
        // category pair. The amount below is intentionally greater than
        // the available budget so that, for an authorized actor, the
        // request would fail with 422 budget_adjustment_insufficient_funds.
        ['workspace' => $workspace, 'category' => $fromCategory] = $this->createUserWithWorkspace();
        $owner = $workspace->owner;
        $toCategory = Category::factory()->forUser($owner)->create();
        $toCategory->workspaces()->attach($workspace->id);

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $fromCategory->id,
            'amount' => '50.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $outsider = User::factory()->create();
        $outsider->assignRole('user');
        $this->actingAsUser($outsider);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments", [
            'from_category_id' => $fromCategory->id,
            'to_category_id' => $toCategory->id,
            'amount' => 999,
            'month' => Carbon::now()->format('Y-m'),
        ])->assertForbidden();

        // Ensure the failure is a pure authorization response, not the
        // domain 422 that would leak the existence/balance of the budget.
        $this->assertNotEquals('budget_adjustment_insufficient_funds', $response->json('code'));

        $this->assertDatabaseCount('budget_adjustments', 0);
    }

    public function test_viewer_cannot_create_adjustment(): void
    {
        ['workspace' => $workspace, 'category' => $fromCategory] = $this->createUserWithWorkspace();
        $owner = $workspace->owner;
        $toCategory = Category::factory()->forUser($owner)->create();
        $toCategory->workspaces()->attach($workspace->id);

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $fromCategory->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $viewer = User::factory()->create();
        $viewer->assignRole('user');
        $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
        $this->actingAsUser($viewer);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments", [
            'from_category_id' => $fromCategory->id,
            'to_category_id' => $toCategory->id,
            'amount' => 50,
            'month' => Carbon::now()->format('Y-m'),
        ])->assertForbidden();

        $this->assertDatabaseCount('budget_adjustments', 0);
    }

    public function test_editor_cannot_create_adjustment(): void
    {
        ['workspace' => $workspace, 'category' => $fromCategory] = $this->createUserWithWorkspace();
        $owner = $workspace->owner;
        $toCategory = Category::factory()->forUser($owner)->create();
        $toCategory->workspaces()->attach($workspace->id);

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $fromCategory->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $editor = User::factory()->create();
        $editor->assignRole('user');
        $workspace->members()->attach($editor->id, ['role' => 'editor']);
        $this->actingAsUser($editor);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments", [
            'from_category_id' => $fromCategory->id,
            'to_category_id' => $toCategory->id,
            'amount' => 50,
            'month' => Carbon::now()->format('Y-m'),
        ])->assertForbidden();

        $this->assertDatabaseCount('budget_adjustments', 0);
    }
}
