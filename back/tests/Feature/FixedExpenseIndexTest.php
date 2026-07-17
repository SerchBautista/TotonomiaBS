<?php

namespace Tests\Feature;

use App\Models\FixedExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class FixedExpenseIndexTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_member_can_list_workspace_fixed_expenses(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        FixedExpense::factory()->count(2)->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/fixed-expenses");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'amount', 'frequency', 'next_due_date', 'is_active']],
            ])
            ->assertJsonCount(2, 'data');
    }

    public function test_non_member_cannot_list_workspace_fixed_expenses(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();

        $outsider = User::factory()->create();
        $outsider->assignRole('user');
        $this->actingAsUser($outsider);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/fixed-expenses")
            ->assertForbidden();
    }
}
