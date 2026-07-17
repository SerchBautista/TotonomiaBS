<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class BudgetAdjustmentAvailableTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_returns_categories_with_available_budget_for_adjustments(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat1] = $this->createUserWithWorkspace();

        $cat2 = Category::factory()->forUser($owner)->create();
        $cat2->workspaces()->attach($workspace->id);

        $cat3 = Category::factory()->forUser($owner)->create();
        $cat3->workspaces()->attach($workspace->id);

        $month = Carbon::now()->startOfMonth();
        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $cat1->id,
            'amount' => '500.00',
            'effective_from' => $month->toDateString(),
        ]);
        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $cat2->id,
            'amount' => '300.00',
            'effective_from' => $month->toDateString(),
        ]);
        // cat3 has no budget — must NOT appear in suggestions.

        $this->actingAsUser($owner);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments/available")
            ->assertOk();

        $data = $response->json('data');

        $this->assertIsArray($data);
        $this->assertCount(2, $data);

        $categoryIds = collect($data)->pluck('category_id')->all();
        $this->assertContains($cat1->id, $categoryIds);
        $this->assertContains($cat2->id, $categoryIds);
        $this->assertNotContains($cat3->id, $categoryIds);

        $cat1Entry = collect($data)->firstWhere('category_id', $cat1->id);
        $this->assertEquals('500.00', $cat1Entry['available']);
        $this->assertEquals('500.00', $cat1Entry['effective_budget']);
        $this->assertEquals('0.00', $cat1Entry['spent']);
    }

    public function test_only_includes_categories_with_budgets_in_workspace(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $fromCategory] = $this->createUserWithWorkspace();

        // Create a SECOND workspace — its budgets must NOT bleed into the
        // suggestions for the first workspace.
        $second = \App\Models\Workspace::factory()->create(['owner_id' => $owner->id]);
        $secondCategory = Category::factory()->forUser($owner)->create();
        $secondCategory->workspaces()->attach($second->id);
        Budget::factory()->create([
            'workspace_id' => $second->id,
            'category_id' => $secondCategory->id,
            'amount' => '999.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $fromCategory->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $this->actingAsUser($owner);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments/available")
            ->assertOk();

        $data = $response->json('data');

        $categoryIds = collect($data)->pluck('category_id')->all();
        $this->assertContains($fromCategory->id, $categoryIds);
        // The second workspace's budget must not appear in this workspace.
        $this->assertNotContains($secondCategory->id, $categoryIds);
    }

    public function test_only_includes_categories_with_remaining_budget(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $withBudget] = $this->createUserWithWorkspace();

        $withoutBudget = Category::factory()->forUser($owner)->create();
        $withoutBudget->workspaces()->attach($workspace->id);

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $withBudget->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        // The second category has NO budget — must be excluded.
        $this->actingAsUser($owner);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments/available")
            ->assertOk();

        $data = $response->json('data');

        $categoryIds = collect($data)->pluck('category_id')->all();
        $this->assertContains($withBudget->id, $categoryIds);
        $this->assertNotContains($withoutBudget->id, $categoryIds);
    }

    public function test_non_member_cannot_access_available(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();

        $outsider = User::factory()->create();
        $outsider->assignRole('user');
        $this->actingAsUser($outsider);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments/available")
            ->assertForbidden();
    }

    public function test_viewer_can_access_available(): void
    {
        // The viewAny policy on BudgetAdjustmentPolicy only requires
        // workspace membership — viewers are allowed.
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat] = $this->createUserWithWorkspace();
        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $cat->id,
            'amount' => '500.00',
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $viewer = User::factory()->create();
        $viewer->assignRole('user');
        $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
        $this->actingAsUser($viewer);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments/available")
            ->assertOk();
    }

    public function test_exclude_category_id_filters_out_a_category(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat1] = $this->createUserWithWorkspace();

        $cat2 = Category::factory()->forUser($owner)->create();
        $cat2->workspaces()->attach($workspace->id);

        $month = Carbon::now()->startOfMonth();
        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $cat1->id,
            'amount' => '500.00',
            'effective_from' => $month->toDateString(),
        ]);
        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $cat2->id,
            'amount' => '300.00',
            'effective_from' => $month->toDateString(),
        ]);

        $this->actingAsUser($owner);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments/available?exclude_category_id={$cat1->id}")
            ->assertOk();

        $data = $response->json('data');
        $categoryIds = collect($data)->pluck('category_id')->all();

        $this->assertNotContains($cat1->id, $categoryIds);
        $this->assertContains($cat2->id, $categoryIds);
    }

    public function test_empty_workspace_returns_empty_data(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();

        // No budgets exist for this workspace
        $this->actingAsUser($owner);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments/available")
            ->assertOk()
            ->assertJsonPath('data', []);
    }
}
