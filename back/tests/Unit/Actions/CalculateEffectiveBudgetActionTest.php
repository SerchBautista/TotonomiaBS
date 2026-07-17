<?php

namespace Tests\Unit\Actions;

use App\Actions\CalculateEffectiveBudgetAction;
use App\Models\Budget;
use App\Models\BudgetAdjustment;
use App\Models\Category;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculateEffectiveBudgetActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeWorkspace(): Workspace
    {
        return Workspace::factory()->create();
    }

    public function test_returns_base_budget_when_no_adjustments(): void
    {
        $workspace = $this->makeWorkspace();
        $category = Category::factory()->create();

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $action = new CalculateEffectiveBudgetAction;

        $result = $action->execute($workspace, $category->id, Carbon::now());

        $this->assertEquals(500.0, $result['base_amount']);
        $this->assertEquals(0.0, $result['adjustments_in']);
        $this->assertEquals(0.0, $result['adjustments_out']);
        $this->assertEquals(500.0, $result['effective_budget']);
    }

    public function test_sums_positive_adjustments_in(): void
    {
        $workspace = $this->makeWorkspace();
        $category = Category::factory()->create();

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '200.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $month = Carbon::now()->startOfMonth();
        $donor = Category::factory()->create();
        // Money in: two adjustments into the same category.
        BudgetAdjustment::create([
            'workspace_id' => $workspace->id,
            'month' => $month,
            'from_category_id' => $donor->id,
            'to_category_id' => $category->id,
            'amount' => 50,
            'user_id' => $workspace->owner_id,
        ]);
        BudgetAdjustment::create([
            'workspace_id' => $workspace->id,
            'month' => $month,
            'from_category_id' => $donor->id,
            'to_category_id' => $category->id,
            'amount' => 30,
            'user_id' => $workspace->owner_id,
        ]);

        $action = new CalculateEffectiveBudgetAction;
        $result = $action->execute($workspace, $category->id, Carbon::now());

        $this->assertEquals(200.0, $result['base_amount']);
        $this->assertEquals(80.0, $result['adjustments_in']);
        $this->assertEquals(0.0, $result['adjustments_out']);
        $this->assertEquals(280.0, $result['effective_budget']);
    }

    public function test_subtracts_negative_adjustments_out(): void
    {
        $workspace = $this->makeWorkspace();
        $category = Category::factory()->create();

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $month = Carbon::now()->startOfMonth();
        $receiver = Category::factory()->create();
        BudgetAdjustment::create([
            'workspace_id' => $workspace->id,
            'month' => $month,
            'from_category_id' => $category->id,
            'to_category_id' => $receiver->id,
            'amount' => 100,
            'user_id' => $workspace->owner_id,
        ]);
        BudgetAdjustment::create([
            'workspace_id' => $workspace->id,
            'month' => $month,
            'from_category_id' => $category->id,
            'to_category_id' => $receiver->id,
            'amount' => 50,
            'user_id' => $workspace->owner_id,
        ]);

        $action = new CalculateEffectiveBudgetAction;
        $result = $action->execute($workspace, $category->id, Carbon::now());

        $this->assertEquals(500.0, $result['base_amount']);
        $this->assertEquals(0.0, $result['adjustments_in']);
        $this->assertEquals(150.0, $result['adjustments_out']);
        $this->assertEquals(350.0, $result['effective_budget']);
    }

    public function test_combines_inbound_and_outbound_adjustments(): void
    {
        $workspace = $this->makeWorkspace();
        $category = Category::factory()->create();

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '1000.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $month = Carbon::now()->startOfMonth();
        $other = Category::factory()->create();

        // 200 in, 300 out → effective = 1000 + 200 - 300 = 900
        BudgetAdjustment::create([
            'workspace_id' => $workspace->id,
            'month' => $month,
            'from_category_id' => $other->id,
            'to_category_id' => $category->id,
            'amount' => 200,
            'user_id' => $workspace->owner_id,
        ]);
        BudgetAdjustment::create([
            'workspace_id' => $workspace->id,
            'month' => $month,
            'from_category_id' => $category->id,
            'to_category_id' => $other->id,
            'amount' => 300,
            'user_id' => $workspace->owner_id,
        ]);

        $action = new CalculateEffectiveBudgetAction;
        $result = $action->execute($workspace, $category->id, Carbon::now());

        $this->assertEquals(1000.0, $result['base_amount']);
        $this->assertEquals(200.0, $result['adjustments_in']);
        $this->assertEquals(300.0, $result['adjustments_out']);
        $this->assertEquals(900.0, $result['effective_budget']);
    }

    public function test_returns_zero_base_when_no_budget(): void
    {
        $workspace = $this->makeWorkspace();
        $category = Category::factory()->create();

        $action = new CalculateEffectiveBudgetAction;
        $result = $action->execute($workspace, $category->id, Carbon::now());

        $this->assertEquals(0.0, $result['base_amount']);
        $this->assertEquals(0.0, $result['adjustments_in']);
        $this->assertEquals(0.0, $result['adjustments_out']);
        $this->assertEquals(0.0, $result['effective_budget']);
    }

    public function test_clamps_effective_budget_to_zero_when_adjustments_drive_it_negative(): void
    {
        $workspace = $this->makeWorkspace();
        $category = Category::factory()->create();

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '100.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $month = Carbon::now()->startOfMonth();
        $other = Category::factory()->create();
        BudgetAdjustment::create([
            'workspace_id' => $workspace->id,
            'month' => $month,
            'from_category_id' => $category->id,
            'to_category_id' => $other->id,
            'amount' => 500,
            'user_id' => $workspace->owner_id,
        ]);

        $action = new CalculateEffectiveBudgetAction;
        $result = $action->execute($workspace, $category->id, Carbon::now());

        // 100 base - 500 out = -400 → clamped to 0.
        $this->assertEquals(0.0, $result['effective_budget']);
    }

    public function test_is_scoped_by_workspace(): void
    {
        $workspaceA = $this->makeWorkspace();
        $workspaceB = $this->makeWorkspace();
        $category = Category::factory()->create();

        Budget::factory()->create([
            'workspace_id' => $workspaceA->id,
            'category_id' => $category->id,
            'amount' => '100.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);
        Budget::factory()->create([
            'workspace_id' => $workspaceB->id,
            'category_id' => $category->id,
            'amount' => '999.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $action = new CalculateEffectiveBudgetAction;
        $result = $action->execute($workspaceA, $category->id, Carbon::now());

        $this->assertEquals(100.0, $result['base_amount']);
    }

    public function test_is_scoped_by_month(): void
    {
        $workspace = $this->makeWorkspace();
        $category = Category::factory()->create();

        // Budget is effective from the previous month so it applies to BOTH
        // the previous month and the current month queries.
        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->subMonth()->startOfMonth()->toDateString(),
        ]);

        $other = Category::factory()->create();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $thisMonth = Carbon::now()->startOfMonth();

        // Adjustment only in the previous month — must not affect the current month.
        BudgetAdjustment::create([
            'workspace_id' => $workspace->id,
            'month' => $lastMonth,
            'from_category_id' => $category->id,
            'to_category_id' => $other->id,
            'amount' => 200,
            'user_id' => $workspace->owner_id,
        ]);
        BudgetAdjustment::create([
            'workspace_id' => $workspace->id,
            'month' => $thisMonth,
            'from_category_id' => $other->id,
            'to_category_id' => $category->id,
            'amount' => 50,
            'user_id' => $workspace->owner_id,
        ]);

        $action = new CalculateEffectiveBudgetAction;

        $last = $action->execute($workspace, $category->id, $lastMonth);
        $current = $action->execute($workspace, $category->id, Carbon::now());

        // Last month: 500 base - 200 out = 300
        $this->assertEquals(300.0, $last['effective_budget']);
        $this->assertEquals(200.0, $last['adjustments_out']);

        // This month: 500 base + 50 in = 550
        $this->assertEquals(550.0, $current['effective_budget']);
        $this->assertEquals(50.0, $current['adjustments_in']);
    }
}
