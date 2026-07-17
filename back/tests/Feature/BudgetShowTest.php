<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class BudgetShowTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_member_can_view_specific_budget(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $budget = Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'amount' => '500.00',
        ]);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/budgets/{$budget->id}");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'amount', 'workspace_id', 'category_id', 'effective_from']])
            ->assertJsonPath('data.id', $budget->id)
            ->assertJsonPath('data.amount', '500.00');
    }

    public function test_non_member_cannot_view_budget(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();
        $budget = Budget::factory()->create(['workspace_id' => $workspace->id]);

        $outsider = User::factory()->create();
        $outsider->assignRole('user');
        $this->actingAsUser($outsider);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/budgets/{$budget->id}")
            ->assertForbidden();
    }

    public function test_view_nonexistent_budget_returns_404(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/budgets/00000000-0000-0000-0000-000000000000")
            ->assertNotFound();
    }
}
