<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\FixedExpense;
use App\Models\FixedExpenseOccurrence;
use App\Models\User;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class BudgetTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    // -----------------------------------------------------------------------
    // CRUD
    // -----------------------------------------------------------------------

    public function test_admin_can_create_general_budget(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/budgets", [
            'amount' => '1000.00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '1000.00')
            ->assertJsonPath('data.category_id', null)
            ->assertJsonPath('data.effective_from', Carbon::now()->startOfMonth()->toDateString());
    }

    public function test_admin_can_create_category_budget(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/budgets", [
            'category_id' => $category->id,
            'amount' => '300.00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.category_id', $category->id);
    }

    public function test_create_category_budget_rejects_category_not_valid_for_shared_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $this->actingAsUser($user);

        $notLinkedCategory = Category::factory()->forUser($user)->create();

        $this->postJson("/api/v1/workspaces/{$workspace->id}/budgets", [
            'category_id' => $notLinkedCategory->id,
            'amount' => '300.00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id'], 'fieldErrors');
    }

    public function test_viewer_cannot_create_budget(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();

        $viewer = User::factory()->create();
        $viewer->assignRole('user');
        $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
        $this->actingAsUser($viewer);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/budgets", [
            'amount' => '500.00',
        ])->assertForbidden();
    }

    public function test_non_member_cannot_access_budgets(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();

        $other = User::factory()->create();
        $other->assignRole('user');
        $this->actingAsUser($other);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/budgets")
            ->assertForbidden();
    }

    public function test_duplicate_scope_and_month_is_rejected(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => null,
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/budgets", [
            'amount' => '999.00',
        ])->assertUnprocessable();
    }

    public function test_member_can_list_budgets(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        Budget::factory()->count(2)->create(['workspace_id' => $workspace->id]);
        $this->actingAsUser($user);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/budgets")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_admin_can_update_budget(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $budget = Budget::factory()->create(['workspace_id' => $workspace->id, 'amount' => '500.00']);
        $this->actingAsUser($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/budgets/{$budget->id}", [
            'amount' => '1500.00',
        ])
            ->assertOk()
            ->assertJsonPath('data.amount', '1500.00');
    }

    public function test_admin_can_delete_budget(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $budget = Budget::factory()->create(['workspace_id' => $workspace->id]);
        $this->actingAsUser($user);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/budgets/{$budget->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('budgets', ['id' => $budget->id]);
    }

    // -----------------------------------------------------------------------
    // Effective-from semantics
    // -----------------------------------------------------------------------

    public function test_budget_persists_to_future_months(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'amount' => '1000.00',
            'effective_from' => '2026-03-01',
        ]);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/budgets-status?month=2026-04")
            ->assertOk();

        $this->assertEquals('1000.00', $response->json('data.general.budget'));
    }

    public function test_newer_budget_replaces_older_for_future_months(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'amount' => '1000.00',
            'effective_from' => '2026-03-01',
        ]);

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'amount' => '1200.00',
            'effective_from' => '2026-06-01',
        ]);

        $may = $this->getJson("/api/v1/workspaces/{$workspace->id}/budgets-status?month=2026-05")
            ->assertOk();
        $this->assertEquals('1000.00', $may->json('data.general.budget'));

        $june = $this->getJson("/api/v1/workspaces/{$workspace->id}/budgets-status?month=2026-06")
            ->assertOk();
        $this->assertEquals('1200.00', $june->json('data.general.budget'));
    }

    public function test_status_returns_null_general_when_no_budget(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/budgets-status")
            ->assertOk()
            ->assertJsonPath('data.general', null);
    }

    // -----------------------------------------------------------------------
    // Status: spent vs budget
    // -----------------------------------------------------------------------

    public function test_status_reflects_correct_spent_amount(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'amount' => '1000.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '300.00',
            'date' => Carbon::now()->toDateString(),
        ]);

        $this->actingAsUser($user);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/budgets-status")
            ->assertOk();

        $this->assertEquals('300.00', $response->json('data.general.spent'));
        $this->assertEquals('700.00', $response->json('data.general.remaining'));
        $this->assertEquals(0.3, $response->json('data.general.percentage'));
        $this->assertTrue($response->json('data.general.over_threshold'));
    }

    // -----------------------------------------------------------------------
    // Budget threshold alerts
    // -----------------------------------------------------------------------

    public function test_expense_creation_returns_warning_when_threshold_crossed(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'amount' => '100.00',
            'alert_threshold' => '0.80',
            'alert_enabled' => true,
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        // Pre-populate to 79%
        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '79.00',
            'date' => Carbon::now()->toDateString(),
        ]);

        $this->actingAsUser($user);

        // This expense pushes to 85%
        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '6.00',
            'date' => Carbon::now()->toDateString(),
        ])->assertCreated();

        $this->assertNotEmpty($response->json('data.budget_warnings'));
        $this->assertEquals('general', $response->json('data.budget_warnings.0.scope'));
    }

    public function test_expense_creation_returns_no_warning_when_under_threshold(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'amount' => '1000.00',
            'alert_threshold' => '0.80',
            'alert_enabled' => true,
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $this->actingAsUser($user);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
            'date' => Carbon::now()->toDateString(),
        ])->assertCreated();

        $this->assertArrayHasKey('budget_warnings', $response->json('data'));
    }

    public function test_expense_creation_returns_no_warning_when_alerts_disabled(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'amount' => '10.00',
            'alert_threshold' => '0.80',
            'alert_enabled' => false,
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $this->actingAsUser($user);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '50.00',
            'date' => Carbon::now()->toDateString(),
        ])->assertCreated();

        $this->assertArrayNotHasKey('budget_warnings', $response->json('data'));
    }

    // -----------------------------------------------------------------------
    // Status: categories without budget but with expenses
    // -----------------------------------------------------------------------

    public function test_status_includes_categories_without_budget_that_have_expenses(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        // Create an expense in a category with NO budget
        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '150.00',
            'date' => Carbon::now()->toDateString(),
        ]);

        $this->actingAsUser($user);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/budgets-status")
            ->assertOk();

        $categoryData = collect($response->json('data.categories'))->firstWhere('category_id', $cat->id);

        $this->assertNotNull($categoryData);
        $this->assertEquals('150.00', $categoryData['spent']);
        $this->assertFalse($categoryData['has_budget']);
    }

    public function test_status_includes_pending_occurrences_in_committed(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        // Create a general budget
        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => null,
            'amount' => '1000.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        // Create a fixed expense with a pending occurrence for current month
        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '150.00',
            'next_due_date' => Carbon::now()->toDateString(),
        ]);

        FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'due_date' => Carbon::now()->toDateString(),
            'suggested_amount' => '150.00',
            'status' => 'pending',
        ]);

        $this->actingAsUser($user);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/budgets-status")
            ->assertOk();

        $this->assertEquals('150.00', $response->json('data.general.committed'));
        $this->assertEquals('150.00', $response->json('data.general.effective_spent'));
    }

    public function test_committed_affects_remaining_calculation(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        // Create a category budget of $500
        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $cat->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        // Create an expense of $200 (spent)
        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '200.00',
            'date' => Carbon::now()->toDateString(),
        ]);

        // Create a pending occurrence of $150 (committed)
        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '150.00',
            'next_due_date' => Carbon::now()->toDateString(),
        ]);

        FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'due_date' => Carbon::now()->toDateString(),
            'suggested_amount' => '150.00',
            'status' => 'pending',
        ]);

        $this->actingAsUser($user);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/budgets-status")
            ->assertOk();

        $categoryData = collect($response->json('data.categories'))->firstWhere('category_id', $cat->id);

        // remaining = effective_budget - spent - committed = 500 - 200 - 150 = 150
        $this->assertEquals('150.00', $categoryData['remaining']);
        // effective_spent = spent + committed = 200 + 150 = 350
        $this->assertEquals('350.00', $categoryData['effective_spent']);
    }

    public function test_status_includes_committed_in_percentage_calculation(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        // Create a category budget of $100
        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $cat->id,
            'amount' => '100.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        // Create a pending occurrence of $50
        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '50.00',
            'next_due_date' => Carbon::now()->toDateString(),
        ]);

        FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'due_date' => Carbon::now()->toDateString(),
            'suggested_amount' => '50.00',
            'status' => 'pending',
        ]);

        $this->actingAsUser($user);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/budgets-status")
            ->assertOk();

        $categoryData = collect($response->json('data.categories'))->firstWhere('category_id', $cat->id);

        // percentage = effective_spent / budget = 50 / 100 = 0.5
        $this->assertEquals(0.5, $categoryData['percentage']);
    }

    public function test_category_without_budget_shows_no_budget_fields(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        // Create an expense but NO budget
        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
            'date' => Carbon::now()->toDateString(),
        ]);

        $this->actingAsUser($user);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/budgets-status")
            ->assertOk();

        $categoryData = collect($response->json('data.categories'))->firstWhere('category_id', $cat->id);

        $this->assertNotNull($categoryData);
        $this->assertFalse($categoryData['has_budget']);
        $this->assertArrayHasKey('spent', $categoryData);
        $this->assertArrayHasKey('committed', $categoryData);
        $this->assertArrayHasKey('effective_spent', $categoryData);
        $this->assertArrayNotHasKey('effective_budget', $categoryData);
        $this->assertArrayNotHasKey('remaining', $categoryData);
        $this->assertArrayNotHasKey('percentage', $categoryData);
        $this->assertArrayNotHasKey('base_budget', $categoryData);
    }

    // -----------------------------------------------------------------------
    // Owner permission regression tests (bug: owner receives 403 on POST budgets)
    // -----------------------------------------------------------------------

    /**
     * Reproduces the original user-reported bug: a user creates a workspace
     * via the real POST /api/v1/workspaces endpoint and then immediately
     * tries to create a budget. The owner must be able to do so.
     */
    public function test_workspace_owner_can_create_budget_after_creating_workspace_via_api(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $this->actingAsUser($user);

        // Create the workspace through the real endpoint — this is the path the
        // user reported. We do not bypass any of the application's setup logic.
        $workspaceResponse = $this->postJson('/api/v1/workspaces', [
            'name' => 'Mi workspace',
            'type' => 'personal',
            'currency_code' => 'MXN',
        ])->assertCreated();

        $workspaceId = $workspaceResponse->json('data.id');

        // Sanity check: the owner must be in the pivot with role 'owner'
        // (CreateWorkspaceAction is responsible for this).
        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        // Now the actual bug: the owner tries to create a budget.
        $this->postJson("/api/v1/workspaces/{$workspaceId}/budgets", [
            'amount' => '1000.00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '1000.00');
    }

    /**
     * Even if the workspace_user pivot is missing or has a stale role
     * (e.g. 'admin' from a legacy CreateDefaultWorkspaceAction), the workspace
     * owner_id must still grant the owner permission to create budgets.
     *
     * This is the defensive policy fallback: owner_id is the source of truth.
     */
    public function test_workspace_owner_can_create_budget_even_when_pivot_role_is_not_owner(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();

        // Simulate the bug: pivot exists but with a role the policy does not allow.
        $workspace->members()->updateExistingPivot($user->id, ['role' => 'admin']);

        $this->actingAsUser($user);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/budgets", [
            'amount' => '750.00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '750.00');
    }

    /**
     * If the pivot is entirely missing for the owner, the owner_id fallback
     * in the policy must still allow budget creation.
     */
    public function test_workspace_owner_can_create_budget_when_pivot_row_is_missing(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();

        // Simulate orphan data: the pivot row for the owner is gone.
        $workspace->members()->detach($user->id);

        $this->assertFalse($workspace->fresh()->hasMember($user->id));

        $this->actingAsUser($user);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/budgets", [
            'amount' => '500.00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '500.00');
    }

    /**
     * The pivot with role 'admin' (e.g. legacy default-workspace seed) must
     * never be exposed to a non-owner user, even if it is present in the pivot.
     * This documents the intent of the role whitelist.
     */
    public function test_pivot_role_admin_does_not_grant_budget_create_to_non_owner_member(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        $member = User::factory()->create();
        $member->assignRole('user');
        // Legacy/wrong role — should NOT be allowed to create budgets.
        $workspace->members()->attach($member->id, ['role' => 'admin']);

        $this->actingAsUser($member);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/budgets", [
            'amount' => '100.00',
        ])->assertForbidden();
    }
}
