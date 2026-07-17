<?php

namespace Tests\Unit\Actions;

use App\Actions\SuggestCategoriesForAdjustmentAction;
use App\Models\Budget;
use App\Models\BudgetAdjustment;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuggestCategoriesForAdjustmentActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeWorkspace(): Workspace
    {
        return Workspace::factory()->create();
    }

    /**
     * Attach a category to a workspace as active (so SuggestCategoriesForAdjustmentAction
     * picks it up after the H-004 fix that requires `category_workspace.is_active = true`).
     */
    private function linkCategoryToWorkspace(Category $category, Workspace $workspace): void
    {
        $category->workspaces()->attach($workspace->id, [
            'is_shared' => true,
            'is_active' => true,
        ]);
    }

    public function test_suggests_categories_with_remaining_budget(): void
    {
        $workspace = $this->makeWorkspace();
        $category = Category::factory()->create();
        $this->linkCategoryToWorkspace($category, $workspace);

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $action = new SuggestCategoriesForAdjustmentAction;
        $result = $action->execute($workspace, '', Carbon::now());

        $this->assertCount(1, $result);
        $this->assertEquals($category->id, $result[0]['category_id']);
        $this->assertEquals('500.00', $result[0]['available']);
    }

    public function test_excludes_categories_with_no_budget(): void
    {
        $workspace = $this->makeWorkspace();
        $withBudget = Category::factory()->create();
        $withoutBudget = Category::factory()->create();
        $this->linkCategoryToWorkspace($withBudget, $workspace);

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $withBudget->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $action = new SuggestCategoriesForAdjustmentAction;
        $result = $action->execute($workspace, '', Carbon::now());

        $categoryIds = collect($result)->pluck('category_id')->all();
        $this->assertContains($withBudget->id, $categoryIds);
        $this->assertNotContains($withoutBudget->id, $categoryIds);
    }

    public function test_excludes_categories_that_are_fully_spent(): void
    {
        $workspace = $this->makeWorkspace();
        $category = Category::factory()->create();
        $this->linkCategoryToWorkspace($category, $workspace);

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '100.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        // Spend the entire budget for the current month.
        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '100.00',
            'date' => Carbon::now()->toDateString(),
        ]);

        $action = new SuggestCategoriesForAdjustmentAction;
        $result = $action->execute($workspace, '', Carbon::now());

        $this->assertCount(0, $result);
    }

    public function test_available_reflects_effective_budget_minus_spent(): void
    {
        $workspace = $this->makeWorkspace();
        $category = Category::factory()->create();
        $this->linkCategoryToWorkspace($category, $workspace);

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '150.00',
            'date' => Carbon::now()->toDateString(),
        ]);

        $action = new SuggestCategoriesForAdjustmentAction;
        $result = $action->execute($workspace, '', Carbon::now());

        $this->assertCount(1, $result);
        $this->assertEquals('350.00', $result[0]['available']);
        $this->assertEquals('150.00', $result[0]['spent']);
        $this->assertEquals('500.00', $result[0]['effective_budget']);
    }

    public function test_includes_inbound_adjustments_in_effective_budget(): void
    {
        $workspace = $this->makeWorkspace();
        $category = Category::factory()->create();
        $donor = Category::factory()->create();
        $this->linkCategoryToWorkspace($category, $workspace);
        $this->linkCategoryToWorkspace($donor, $workspace);

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '200.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        BudgetAdjustment::create([
            'workspace_id' => $workspace->id,
            'month' => Carbon::now()->startOfMonth(),
            'from_category_id' => $donor->id,
            'to_category_id' => $category->id,
            'amount' => 75,
            'user_id' => $workspace->owner_id,
        ]);

        $action = new SuggestCategoriesForAdjustmentAction;
        $result = $action->execute($workspace, '', Carbon::now());

        $this->assertCount(1, $result);
        // effective_budget = 200 + 75 = 275
        $this->assertEquals('275.00', $result[0]['effective_budget']);
        $this->assertEquals('275.00', $result[0]['available']);
    }

    public function test_subtracts_outbound_adjustments_in_effective_budget(): void
    {
        $workspace = $this->makeWorkspace();
        $category = Category::factory()->create();
        $receiver = Category::factory()->create();
        $this->linkCategoryToWorkspace($category, $workspace);

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        BudgetAdjustment::create([
            'workspace_id' => $workspace->id,
            'month' => Carbon::now()->startOfMonth(),
            'from_category_id' => $category->id,
            'to_category_id' => $receiver->id,
            'amount' => 100,
            'user_id' => $workspace->owner_id,
        ]);

        $action = new SuggestCategoriesForAdjustmentAction;
        $result = $action->execute($workspace, '', Carbon::now());

        $this->assertCount(1, $result);
        // effective_budget = 500 - 100 = 400
        $this->assertEquals('400.00', $result[0]['effective_budget']);
        $this->assertEquals('400.00', $result[0]['available']);
    }

    public function test_is_scoped_by_workspace(): void
    {
        $workspaceA = $this->makeWorkspace();
        $workspaceB = $this->makeWorkspace();

        $catA = Category::factory()->create();
        $catB = Category::factory()->create();
        $this->linkCategoryToWorkspace($catA, $workspaceA);
        $this->linkCategoryToWorkspace($catB, $workspaceB);

        Budget::factory()->create([
            'workspace_id' => $workspaceA->id,
            'category_id' => $catA->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);
        Budget::factory()->create([
            'workspace_id' => $workspaceB->id,
            'category_id' => $catB->id,
            'amount' => '999.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $action = new SuggestCategoriesForAdjustmentAction;
        $result = $action->execute($workspaceA, '', Carbon::now());

        $categoryIds = collect($result)->pluck('category_id')->all();
        $this->assertContains($catA->id, $categoryIds);
        $this->assertNotContains($catB->id, $categoryIds);
    }

    public function test_exclude_category_id_excludes_a_category(): void
    {
        $workspace = $this->makeWorkspace();
        $cat1 = Category::factory()->create();
        $cat2 = Category::factory()->create();
        $this->linkCategoryToWorkspace($cat1, $workspace);
        $this->linkCategoryToWorkspace($cat2, $workspace);

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $cat1->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);
        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $cat2->id,
            'amount' => '300.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $action = new SuggestCategoriesForAdjustmentAction;
        $result = $action->execute($workspace, $cat1->id, Carbon::now());

        $categoryIds = collect($result)->pluck('category_id')->all();
        $this->assertNotContains($cat1->id, $categoryIds);
        $this->assertContains($cat2->id, $categoryIds);
    }

    public function test_results_sorted_by_available_descending(): void
    {
        $workspace = $this->makeWorkspace();
        $cat1 = Category::factory()->create();
        $cat2 = Category::factory()->create();
        $cat3 = Category::factory()->create();
        $this->linkCategoryToWorkspace($cat1, $workspace);
        $this->linkCategoryToWorkspace($cat2, $workspace);
        $this->linkCategoryToWorkspace($cat3, $workspace);

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $cat1->id,
            'amount' => '100.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);
        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $cat2->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);
        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $cat3->id,
            'amount' => '300.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $action = new SuggestCategoriesForAdjustmentAction;
        $result = $action->execute($workspace, '', Carbon::now());

        $this->assertCount(3, $result);
        $availables = collect($result)->pluck('available')->all();
        $this->assertEquals(['500.00', '300.00', '100.00'], $availables);
    }

    public function test_returns_empty_array_when_workspace_has_no_budgets(): void
    {
        $workspace = $this->makeWorkspace();

        $action = new SuggestCategoriesForAdjustmentAction;
        $result = $action->execute($workspace, '', Carbon::now());

        $this->assertSame([], $result);
    }

    public function test_only_uses_expenses_in_target_month(): void
    {
        $workspace = $this->makeWorkspace();
        $category = Category::factory()->create();
        $this->linkCategoryToWorkspace($category, $workspace);

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        // An expense in the PREVIOUS month must not reduce this month's
        // available amount.
        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '400.00',
            'date' => Carbon::now()->subMonth()->toDateString(),
        ]);

        $action = new SuggestCategoriesForAdjustmentAction;
        $result = $action->execute($workspace, '', Carbon::now());

        $this->assertCount(1, $result);
        $this->assertEquals('500.00', $result[0]['available']);
        $this->assertEquals('0.00', $result[0]['spent']);
    }

    public function test_only_includes_categories_with_budgets_linked_and_active_in_workspace(): void
    {
        // After H-004 fix: the action must only return categories whose
        // pivot row in `category_workspace` is `is_active = true` for the
        // target workspace. A category with a budget but unlinked (or
        // inactive) must NOT be suggested.
        $workspace = $this->makeWorkspace();

        $linkedActive = Category::factory()->create();
        $this->linkCategoryToWorkspace($linkedActive, $workspace);

        $linkedInactive = Category::factory()->create();
        $linkedInactive->workspaces()->attach($workspace->id, [
            'is_shared' => true,
            'is_active' => false,
        ]);

        $unlinked = Category::factory()->create();

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $linkedActive->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);
        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $linkedInactive->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);
        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $unlinked->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $action = new SuggestCategoriesForAdjustmentAction;
        $result = $action->execute($workspace, '', Carbon::now());

        $categoryIds = collect($result)->pluck('category_id')->all();
        $this->assertContains($linkedActive->id, $categoryIds);
        $this->assertNotContains($linkedInactive->id, $categoryIds);
        $this->assertNotContains($unlinked->id, $categoryIds);
        $this->assertCount(1, $result);
    }
}
