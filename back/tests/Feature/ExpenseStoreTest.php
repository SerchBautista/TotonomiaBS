<?php

namespace Tests\Feature;

use App\Models\Budget;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class ExpenseStoreTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_store_returns_expense_via_resource_envelope(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();

        $this->actingAsUser($owner);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'amount' => 25.50,
            'date' => Carbon::now()->toDateString(),
            'description' => 'Coffee',
        ])
            ->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'workspace_id',
                    'amount',
                    'date',
                    'description',
                    'category',
                    'payment_type',
                    'created_at',
                    'updated_at',
                ],
            ]);

        $this->assertArrayNotHasKey('budget_warnings', $response->json());
        $this->assertSame('25.50', $response->json('data.amount'));
    }

    public function test_store_includes_budget_warnings_inside_data_object(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => 100,
            'alert_threshold' => 50,
            'alert_enabled' => true,
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        $this->actingAsUser($owner);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'amount' => 60,
            'date' => Carbon::now()->toDateString(),
        ])->assertCreated();

        $this->assertArrayNotHasKey('budget_warnings', $response->json());
        $this->assertArrayHasKey('budget_warnings', $response->json('data'));
        $this->assertNotEmpty($response->json('data.budget_warnings'));
        $this->assertSame('category', $response->json('data.budget_warnings.0.scope'));
    }
}
