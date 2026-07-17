<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Category;
use App\Models\FixedExpense;
use App\Models\FixedExpenseOccurrence;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class FixedExpenseTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_can_create_fixed_expense_with_valid_category_for_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/fixed-expenses", [
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
            'frequency' => 'monthly',
            'next_due_date' => now()->addDay()->toDateString(),
        ])->assertCreated();
    }

    public function test_can_create_fixed_expense_with_cash_without_linked_payment_method(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $this->actingAsUser($user);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/fixed-expenses", [
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'amount' => '100.00',
            'frequency' => 'monthly',
            'next_due_date' => now()->addDay()->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonFragment(['payment_type' => 'cash']);

        $this->assertDatabaseHas('fixed_expenses', [
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
            'amount' => '100.00',
        ]);
    }

    public function test_create_fixed_expense_rejects_category_not_valid_for_shared_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'card' => $card] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $this->actingAsUser($user);

        $notLinkedCategory = Category::factory()->forUser($user)->create();

        $this->postJson("/api/v1/workspaces/{$workspace->id}/fixed-expenses", [
            'category_id' => $notLinkedCategory->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
            'frequency' => 'monthly',
            'next_due_date' => now()->addDay()->toDateString(),
        ])
            ->assertUnprocessable()
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('fieldErrors.category_id.0', 'The selected category is invalid for the current workspace.');
    }

    public function test_update_fixed_expense_rejects_category_not_valid_for_shared_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace(['type' => 'empresa']);

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'frequency' => 'monthly',
            'next_due_date' => now()->addDay()->toDateString(),
        ]);

        $notLinkedCategory = Category::factory()->forUser($user)->create();

        $this->actingAsUser($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/fixed-expenses/{$fixedExpense->id}", [
            'category_id' => $notLinkedCategory->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('fieldErrors.category_id.0', 'The selected category is invalid for the current workspace.');
    }

    public function test_update_fixed_expense_with_paid_occurrences_returns_standard_validation_error(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace();

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'frequency' => 'monthly',
            'next_due_date' => now()->addDay()->toDateString(),
        ]);

        FixedExpenseOccurrence::factory()->paid()->create([
            'fixed_expense_id' => $fixedExpense->id,
        ]);

        $this->actingAsUser($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/fixed-expenses/{$fixedExpense->id}", [
            'description' => 'Updated description',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath(
                'fieldErrors.fixed_expense.0',
                __('api.fixed_expenses.cannot_update_with_paid_occurrences'),
            );
    }

    /**
     * The workspace owner must always be able to create fixed expenses,
     * even if the workspace_user pivot has a role the policy does not whitelist.
     */
    public function test_workspace_owner_can_create_fixed_expense_when_pivot_role_is_not_owner(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace();

        $workspace->members()->updateExistingPivot($user->id, ['role' => 'admin']);

        $this->actingAsUser($user);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/fixed-expenses", [
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
            'frequency' => 'monthly',
            'next_due_date' => now()->addDay()->toDateString(),
        ])->assertCreated();
    }

    /**
     * If the pivot row for the owner is entirely missing, the owner_id fallback
     * in the policy must still allow fixed-expense creation.
     */
    public function test_workspace_owner_can_create_fixed_expense_when_pivot_row_is_missing(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace();

        $workspace->members()->detach($user->id);

        $this->assertFalse($workspace->fresh()->hasMember($user->id));

        $this->actingAsUser($user);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/fixed-expenses", [
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
            'frequency' => 'monthly',
            'next_due_date' => now()->addDay()->toDateString(),
        ])->assertCreated();
    }

    /**
     * A non-owner member with a legacy 'admin' pivot role must not be able to
     * create fixed expenses; only the owner or an explicit guest can.
     */
    public function test_pivot_role_admin_does_not_grant_fixed_expense_create_to_non_owner_member(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        $category = Category::factory()->forUser($owner)->create();
        $category->workspaces()->attach($workspace->id);

        $card = Card::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
        $card->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        $member = User::factory()->create();
        $member->assignRole('user');
        $workspace->members()->attach($member->id, ['role' => 'admin']);

        $this->actingAsUser($member);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/fixed-expenses", [
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
            'frequency' => 'monthly',
            'next_due_date' => now()->addDay()->toDateString(),
        ])->assertForbidden();
    }
}
