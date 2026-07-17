<?php

namespace Tests\Feature;

use App\Models\BudgetAdjustment;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class BudgetAdjustmentTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_index_returns_single_data_envelope_without_nested_data(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $fromCategory] = $this->createUserWithWorkspace();
        $toCategory = Category::factory()->forUser($owner)->create();
        $toCategory->workspaces()->attach($workspace->id);

        $month = Carbon::now()->startOfMonth();

        BudgetAdjustment::create([
            'workspace_id' => $workspace->id,
            'month' => $month,
            'from_category_id' => $fromCategory->id,
            'to_category_id' => $toCategory->id,
            'amount' => 150.00,
            'reason' => 'Rebalance',
            'user_id' => $owner->id,
        ]);

        $this->actingAsUser($owner);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments?month=".$month->format('Y-m'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'workspace_id',
                        'month',
                        'from_category_id',
                        'to_category_id',
                        'amount',
                        'reason',
                        'user_id',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        $this->assertIsArray($response->json('data'));
        $this->assertArrayNotHasKey('data', $response->json('data'));
        $response->assertJsonMissingPath('data.data');
    }

    public function test_index_returns_403_for_non_member(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();
        $outsider = \App\Models\User::factory()->create();
        $outsider->assignRole('user');

        $this->actingAsUser($outsider);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/budget-adjustments")
            ->assertForbidden();
    }
}
