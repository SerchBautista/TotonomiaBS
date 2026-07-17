<?php

namespace Tests\Unit\Actions;

use App\Actions\CheckBudgetThresholdAction;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckBudgetThresholdActionTest extends TestCase
{
    use RefreshDatabase;

    private function makeWorkspace(): Workspace
    {
        return Workspace::factory()->create();
    }

    public function test_returns_warning_when_general_threshold_is_crossed(): void
    {
        $workspace = $this->makeWorkspace();

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => null,
            'amount' => '100.00',
            'alert_threshold' => '80.00',
            'alert_enabled' => true,
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $category = Category::factory()->create();
        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '90.00',
            'date' => Carbon::now()->toDateString(),
        ]);

        $action = new CheckBudgetThresholdAction(
            app(\App\Contracts\CalculateEffectiveBudgetActionInterface::class)
        );

        $warnings = $action->execute($workspace, $category->id, Carbon::now()->toDateString());

        $this->assertCount(1, $warnings);
        $this->assertEquals('general', $warnings[0]['scope']);
        $this->assertEquals('100.00', $warnings[0]['budget']);
        $this->assertEquals('90.00', $warnings[0]['spent']);
        $this->assertEquals(80.0, $warnings[0]['threshold']);
    }

    public function test_returns_no_warning_when_alerts_disabled(): void
    {
        $workspace = $this->makeWorkspace();
        $category = Category::factory()->create();

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => null,
            'amount' => '100.00',
            'alert_threshold' => '80.00',
            'alert_enabled' => false,
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '90.00',
            'date' => Carbon::now()->toDateString(),
        ]);

        $action = new CheckBudgetThresholdAction(
            app(\App\Contracts\CalculateEffectiveBudgetActionInterface::class)
        );

        $warnings = $action->execute($workspace, $category->id, Carbon::now()->toDateString());

        $this->assertSame([], $warnings);
    }

    public function test_returns_no_warning_when_under_threshold(): void
    {
        $workspace = $this->makeWorkspace();
        $category = Category::factory()->create();

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => null,
            'amount' => '1000.00',
            'alert_threshold' => '900.00', // absolute amount — must be > 100
            'alert_enabled' => true,
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '100.00',
            'date' => Carbon::now()->toDateString(),
        ]);

        $action = new CheckBudgetThresholdAction(
            app(\App\Contracts\CalculateEffectiveBudgetActionInterface::class)
        );

        $warnings = $action->execute($workspace, $category->id, Carbon::now()->toDateString());

        $this->assertSame([], $warnings);
    }

    public function test_returns_no_warning_when_no_budget_exists(): void
    {
        $workspace = $this->makeWorkspace();
        $category = Category::factory()->create();

        // No budget at all.
        $action = new CheckBudgetThresholdAction(
            app(\App\Contracts\CalculateEffectiveBudgetActionInterface::class)
        );

        $warnings = $action->execute($workspace, $category->id, Carbon::now()->toDateString());

        $this->assertSame([], $warnings);
    }

    public function test_returns_no_warning_when_budget_is_zero(): void
    {
        $workspace = $this->makeWorkspace();
        $category = Category::factory()->create();

        // A zero budget with zero spent must not fire any warning.
        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => null,
            'amount' => '0.00',
            'alert_threshold' => '0.00',
            'alert_enabled' => true,
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $action = new CheckBudgetThresholdAction(
            app(\App\Contracts\CalculateEffectiveBudgetActionInterface::class)
        );

        $warnings = $action->execute($workspace, $category->id, Carbon::now()->toDateString());

        $this->assertSame([], $warnings);
    }

    public function test_returns_warning_when_category_threshold_is_crossed(): void
    {
        $workspace = $this->makeWorkspace();
        $category = Category::factory()->create();

        // General budget high enough to stay quiet.
        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => null,
            'amount' => '10000.00',
            'alert_threshold' => '9000.00',
            'alert_enabled' => true,
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        // Category budget of 100 with a fixed 80 threshold.
        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '100.00',
            'alert_threshold' => '80.00',
            'alert_enabled' => true,
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        // Spent 85 in this category — above the fixed 80 threshold.
        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '85.00',
            'date' => Carbon::now()->toDateString(),
        ]);

        $action = new CheckBudgetThresholdAction(
            app(\App\Contracts\CalculateEffectiveBudgetActionInterface::class)
        );

        $warnings = $action->execute($workspace, $category->id, Carbon::now()->toDateString());

        $categoryWarning = collect($warnings)->firstWhere('scope', 'category');
        $this->assertNotNull($categoryWarning);
        $this->assertEquals(80.0, $categoryWarning['threshold']);
    }

    public function test_returns_warning_when_category_is_over_budget_even_under_threshold(): void
    {
        $workspace = $this->makeWorkspace();
        $category = Category::factory()->create();

        // High general budget — won't fire.
        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => null,
            'amount' => '10000.00',
            'alert_threshold' => '9000.00',
            'alert_enabled' => true,
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        // Category budget 100 with threshold 200 (won't cross by 150 spend).
        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '100.00',
            'alert_threshold' => '200.00',
            'alert_enabled' => true,
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        // Spent 150 — under threshold (200) but over budget (100).
        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '150.00',
            'date' => Carbon::now()->toDateString(),
        ]);

        $action = new CheckBudgetThresholdAction(
            app(\App\Contracts\CalculateEffectiveBudgetActionInterface::class)
        );

        $warnings = $action->execute($workspace, $category->id, Carbon::now()->toDateString());

        $categoryWarning = collect($warnings)->firstWhere('scope', 'category');
        $this->assertNotNull($categoryWarning);
        $this->assertTrue($categoryWarning['over_budget']);
    }

    public function test_no_category_warning_when_alerts_disabled_for_category_budget(): void
    {
        $workspace = $this->makeWorkspace();
        $category = Category::factory()->create();

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '100.00',
            'alert_threshold' => '80.00',
            'alert_enabled' => false,
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => '90.00',
            'date' => Carbon::now()->toDateString(),
        ]);

        $action = new CheckBudgetThresholdAction(
            app(\App\Contracts\CalculateEffectiveBudgetActionInterface::class)
        );

        $warnings = $action->execute($workspace, $category->id, Carbon::now()->toDateString());

        $this->assertSame([], $warnings);
    }
}
