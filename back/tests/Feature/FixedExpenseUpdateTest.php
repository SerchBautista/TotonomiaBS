<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\FixedExpense;
use App\Models\FixedExpenseOccurrence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class FixedExpenseUpdateTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_owner_can_update_fixed_expense(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
        ]);

        $response = $this->putJson("/api/v1/workspaces/{$workspace->id}/fixed-expenses/{$fixedExpense->id}", [
            'amount' => '250.00',
            'description' => 'Updated',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.amount', '250.00')
            ->assertJsonPath('data.description', 'Updated');

        $this->assertDatabaseHas('fixed_expenses', [
            'id' => $fixedExpense->id,
            'amount' => '250.00',
            'description' => 'Updated',
        ]);
    }

    public function test_update_fixed_expense_with_invalid_category_returns_422(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $this->actingAsUser($user);

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $notLinkedCategory = Category::factory()->forUser($user)->create();

        $response = $this->putJson("/api/v1/workspaces/{$workspace->id}/fixed-expenses/{$fixedExpense->id}", [
            'category_id' => $notLinkedCategory->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['fieldErrors' => ['category_id']]);
    }

    public function test_update_fixed_expense_with_paid_occurrences_returns_422(): void
    {
        // The current behaviour of UpdateFixedExpenseRequest is to add a
        // custom validation error in the `after()` hook when the expense
        // already has at least one paid occurrence. The spec asked us to
        // verify the real behaviour, which is a 422 with the translation
        // key `api.fixed_expenses.cannot_update_with_paid_occurrences`.
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

        $response = $this->putJson("/api/v1/workspaces/{$workspace->id}/fixed-expenses/{$fixedExpense->id}", [
            'description' => 'Try to update',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath(
                'fieldErrors.fixed_expense.0',
                __('api.fixed_expenses.cannot_update_with_paid_occurrences'),
            );
    }

    public function test_non_member_cannot_update_fixed_expense(): void
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

        $this->putJson("/api/v1/workspaces/{$workspace->id}/fixed-expenses/{$fixedExpense->id}", [
            'amount' => '999.99',
        ])->assertForbidden();
    }
}
