<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Category;
use App\Models\Expense;
use App\Models\FixedExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class ExpenseUpdateExtendedTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_owner_can_update_expense(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
            'description' => 'Antes',
        ]);

        $this->actingAsUser($owner);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}", [
            'amount' => '150.00',
            'description' => 'Después',
        ])
            ->assertOk()
            ->assertJsonPath('data.amount', '150.00')
            ->assertJsonPath('data.description', 'Después');

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'amount' => '150.00',
            'description' => 'Después',
        ]);
    }

    public function test_viewer_cannot_update_expense_they_did_not_create(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();
        $viewer = User::factory()->create();
        $workspace->members()->attach($viewer->id, ['role' => 'viewer']);

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
        ]);

        $this->actingAsUser($viewer);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}", [
            'amount' => '200.00',
        ])->assertForbidden();

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'amount' => '100.00',
        ]);
    }

    public function test_update_rejects_category_not_valid_for_workspace(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace(['type' => 'familiar']);

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $notLinkedCategory = Category::factory()->forUser($owner)->create();

        $this->actingAsUser($owner);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}", [
            'category_id' => $notLinkedCategory->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('fieldErrors.category_id.0', 'The selected category is invalid for the current workspace.');
    }

    public function test_update_rejects_category_inactive_in_workspace(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace(['type' => 'familiar']);

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $cat->workspaces()->updateExistingPivot($workspace->id, [
            'is_active' => false,
        ]);

        $this->actingAsUser($owner);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}", [
            'category_id' => $cat->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('fieldErrors.category_id.0', 'The selected category is invalid for the current workspace.');
    }

    public function test_update_rejects_future_date(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'date' => '2026-01-15',
        ]);

        $this->actingAsUser($owner);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}", [
            'date' => '2099-12-31',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('fieldErrors.date.0', 'The date field must not be in the future.');
    }

    public function test_update_rejects_zero_amount(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
        ]);

        $this->actingAsUser($owner);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}", [
            'amount' => '0',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error');
    }

    public function test_update_rejects_negative_amount(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
        ]);

        $this->actingAsUser($owner);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}", [
            'amount' => '-1.00',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error');
    }

    public function test_update_rejects_payment_instrument_not_linked_to_workspace(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace(['type' => 'familiar']);

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        // Deactivate the card in the workspace pivot.
        $card->workspaces()->updateExistingPivot($workspace->id, [
            'is_shared' => false,
            'is_active' => false,
        ]);

        $this->actingAsUser($owner);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}", [
            'payment_instrument_id' => $card->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('fieldErrors.payment_instrument_id.0', 'The selected payment method is invalid for the current workspace.');
    }

    public function test_update_rejects_invalid_payment_type(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $this->actingAsUser($owner);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}", [
            'payment_type' => 'invalid',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error');
    }

    public function test_update_by_foreign_workspace_member_returns_403(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
        ]);

        // Outsider: a user in a different workspace.
        $outsider = User::factory()->create();
        $otherWorkspace = \App\Models\Workspace::factory()->create(['owner_id' => $outsider->id]);
        $otherWorkspace->members()->attach($outsider->id, ['role' => 'owner']);

        $this->actingAsUser($outsider);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}", [
            'amount' => '200.00',
        ])->assertForbidden();

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'amount' => '100.00',
        ]);
    }

    public function test_update_partial_amount_only(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
            'description' => 'Original',
            'date' => '2026-03-15',
        ]);

        $this->actingAsUser($owner);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}", [
            'amount' => '250.00',
        ])
            ->assertOk()
            ->assertJsonPath('data.amount', '250.00')
            ->assertJsonPath('data.description', 'Original')
            ->assertJsonPath('data.date', '2026-03-15');
    }

    public function test_update_partial_date_only(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
            'description' => 'Original',
            'date' => '2026-03-15',
        ]);

        $this->actingAsUser($owner);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}", [
            'date' => '2026-04-20',
        ])
            ->assertOk()
            ->assertJsonPath('data.amount', '100.00')
            ->assertJsonPath('data.description', 'Original')
            ->assertJsonPath('data.date', '2026-04-20');
    }

    public function test_update_partial_description_only(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
            'description' => 'Original',
        ]);

        $this->actingAsUser($owner);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}", [
            'description' => 'Nueva descripción',
        ])
            ->assertOk()
            ->assertJsonPath('data.description', 'Nueva descripción')
            ->assertJsonPath('data.amount', '100.00');
    }

    public function test_update_expense_linked_to_paid_fixed_expense_respects_validation(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace(['type' => 'familiar']);

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        // The expense is linked to a paid fixed expense.
        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'fixed_expense_id' => $fixedExpense->id,
            'amount' => '500.00',
        ]);

        $occurrence = \App\Models\FixedExpenseOccurrence::factory()->paid()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'expense_id' => $expense->id,
        ]);

        $this->actingAsUser($owner);

        // Valid update: change amount, validation must succeed even when
        // the expense is linked to a paid fixed expense.
        $this->putJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}", [
            'amount' => '450.00',
        ])
            ->assertOk()
            ->assertJsonPath('data.amount', '450.00');

        // Verify the occurrence still references the same expense id.
        $this->assertSame($expense->id, $occurrence->fresh()->expense_id);

        // Invalid update: future date must be rejected.
        $this->putJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}", [
            'date' => '2099-12-31',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('fieldErrors.date.0', 'The date field must not be in the future.');
    }

    public function test_update_keeps_other_fields_unchanged(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
            'description' => 'No debe cambiar',
            'date' => '2026-03-15',
        ]);

        $this->actingAsUser($owner);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}", [
            'description' => 'Tampoco debe cambiar',
        ])
            ->assertOk();

        $expense->refresh();
        $this->assertSame('100.00', (string) $expense->amount);
        $this->assertSame('2026-03-15', $expense->date->toDateString());
    }
}
