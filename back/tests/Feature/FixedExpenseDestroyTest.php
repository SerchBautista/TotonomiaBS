<?php

namespace Tests\Feature;

use App\Models\FixedExpense;
use App\Models\FixedExpenseOccurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class FixedExpenseDestroyTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_owner_can_delete_fixed_expense_without_occurrences(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/fixed-expenses/{$fixedExpense->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('fixed_expenses', ['id' => $fixedExpense->id]);
    }

    public function test_destroy_fixed_expense_with_paid_occurrences_returns_422_fixed_expense_has_paid_occurrences(): void
    {
        // H-011 fix: FixedExpenseController::destroy must mirror the
        // protection that UpdateFixedExpenseRequest already provides — a
        // fixed expense that has paid occurrences cannot be deleted. We
        // return 422 with code `fixed_expense_has_paid_occurrences` to keep
        // semantic parity with the update path.
        ['user' => $user, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        FixedExpenseOccurrence::factory()->paid()->create([
            'fixed_expense_id' => $fixedExpense->id,
        ]);

        $response = $this->deleteJson("/api/v1/workspaces/{$workspace->id}/fixed-expenses/{$fixedExpense->id}");

        $response->assertStatus(422)
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'fixed_expense_has_paid_occurrences');

        $this->assertNotSoftDeleted('fixed_expenses', ['id' => $fixedExpense->id]);
    }

    public function test_non_member_cannot_delete_fixed_expense(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace();

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $outsider = User::factory()->create();
        $outsider->assignRole('user');
        $this->actingAsUser($outsider);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/fixed-expenses/{$fixedExpense->id}")
            ->assertForbidden();
    }
}
