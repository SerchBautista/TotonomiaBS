<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class ExpenseTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_workspace_member_can_list_expenses(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        Expense::factory()->count(3)->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $this->actingAsUser($user);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/expenses")
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_admin_member_can_create_expense(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '150.50',
            'date' => '2026-03-01',
            'description' => 'Compras supermercado',
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['amount' => '150.50']);

        $this->assertDatabaseHas('expenses', [
            'workspace_id' => $workspace->id,
            'amount' => '150.50',
        ]);
    }

    public function test_admin_member_can_create_expense_with_cash_without_linked_payment_method(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $this->actingAsUser($user);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'category_id' => $cat->id,
            'payment_type' => 'cash',
            'amount' => '150.50',
            'date' => '2026-03-01',
            'description' => 'Pago en efectivo',
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['payment_type' => 'cash']);

        $this->assertDatabaseHas('expenses', [
            'workspace_id' => $workspace->id,
            'category_id' => $cat->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
            'amount' => '150.50',
        ]);
    }

    public function test_create_expense_rejects_category_not_valid_for_shared_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'card' => $card] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $this->actingAsUser($user);

        $notLinkedCategory = Category::factory()->forUser($user)->create();

        $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'category_id' => $notLinkedCategory->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '150.50',
            'date' => '2026-03-01',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('fieldErrors.category_id.0', 'The selected category is invalid for the current workspace.');
    }

    public function test_create_expense_rejects_card_that_is_not_linked_and_active_in_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $card->workspaces()->updateExistingPivot($workspace->id, [
            'is_shared' => false,
            'is_active' => false,
        ]);

        $this->actingAsUser($user);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '150.50',
            'date' => '2026-03-01',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('fieldErrors.payment_instrument_id.0', 'The selected payment method is invalid for the current workspace.');
    }

    public function test_viewer_member_cannot_create_expense(): void
    {
        ['workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();
        $viewer = User::factory()->create();
        $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
        $this->actingAsUser($viewer);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
            'date' => '2026-03-01',
        ])->assertForbidden();
    }

    public function test_non_member_cannot_list_expenses(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();
        $outsider = User::factory()->create();
        $this->actingAsUser($outsider);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/expenses")
            ->assertForbidden();
    }

    public function test_expense_can_be_created_with_client_uuid_for_offline_first(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);
        $clientUuid = Str::uuid()->toString();

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'id' => $clientUuid,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '75.00',
            'date' => '2026-03-01',
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['id' => $clientUuid]);

        $this->assertDatabaseHas('expenses', ['id' => $clientUuid]);
    }

    public function test_expense_owner_can_delete_expense(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();
        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);
        $this->actingAsUser($user);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
    }

    public function test_update_expense_rejects_category_not_valid_for_shared_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace(['type' => 'empresa']);

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $notLinkedCategory = Category::factory()->forUser($user)->create();

        $this->actingAsUser($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}", [
            'category_id' => $notLinkedCategory->id,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('fieldErrors.category_id.0', 'The selected category is invalid for the current workspace.');
    }

    public function test_expense_amount_validates_max_precision(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        // Amount exceeds NUMERIC(15,2) max
        $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '99999999999999.99',
            'date' => '2026-03-01',
        ])->assertUnprocessable();
    }

    public function test_list_expenses_can_be_filtered_by_payment_type(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
        ]);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $this->actingAsUser($user);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/expenses?payment_type=cash")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['payment_type' => 'cash']);
    }

    public function test_list_expenses_can_be_filtered_by_search(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'description' => 'Supermarket purchase',
        ]);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'description' => 'Gas station',
        ]);

        $this->actingAsUser($user);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/expenses?search=super")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['description' => 'Supermarket purchase']);
    }

    public function test_list_expenses_invalid_payment_type_is_rejected(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/expenses?payment_type=invalid")
            ->assertUnprocessable();
    }

    public function test_list_expenses_rejects_category_filter_not_valid_for_shared_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $this->actingAsUser($user);

        $notLinkedCategory = Category::factory()->forUser($user)->create();

        $this->getJson("/api/v1/workspaces/{$workspace->id}/expenses?category_id={$notLinkedCategory->id}")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('fieldErrors.category_id.0', 'The selected category is invalid for the current workspace.');
    }

    public function test_total_endpoint_returns_sum_of_filtered_expenses(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
            'date' => now()->toDateString(),
        ]);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
            'amount' => '50.00',
            'date' => now()->toDateString(),
        ]);

        $this->actingAsUser($user);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/expenses/total")
            ->assertOk()
            ->assertJsonFragment(['total' => '150']);
    }

    public function test_total_endpoint_respects_payment_type_filter(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
            'date' => now()->toDateString(),
        ]);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
            'amount' => '50.00',
            'date' => now()->toDateString(),
        ]);

        $this->actingAsUser($user);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/expenses/total?payment_type=card")
            ->assertOk()
            ->assertJsonFragment(['total' => '100']);
    }

    public function test_total_endpoint_returns_zero_when_no_expenses_match(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/expenses/total")
            ->assertOk()
            ->assertJsonFragment(['total' => '0']);
    }

    public function test_total_endpoint_rejects_category_filter_not_valid_for_shared_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace(['type' => 'empresa']);
        $this->actingAsUser($user);

        $notLinkedCategory = Category::factory()->forUser($user)->create();

        $this->getJson("/api/v1/workspaces/{$workspace->id}/expenses/total?category_id={$notLinkedCategory->id}")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('fieldErrors.category_id.0', 'The selected category is invalid for the current workspace.');
    }

    public function test_cannot_create_expense_with_future_date(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
            'date' => '2099-12-31',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('fieldErrors.date.0', 'The date field must not be in the future.');
    }

    public function test_cannot_update_expense_with_future_date(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();
        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'date' => '2026-01-15',
        ]);
        $this->actingAsUser($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}", [
            'date' => '2099-12-31',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('fieldErrors.date.0', 'The date field must not be in the future.');
    }

    public function test_can_create_expense_with_today_date_in_user_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 23:30:00'));

        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();
        $user->timezone = 'America/Mexico_City';
        $user->save();
        $this->actingAsUser($user);

        $todayInMexico = Carbon::now('America/Mexico_City')->toDateString();

        $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
            'date' => $todayInMexico,
        ])->assertCreated();

        Carbon::setTestNow(null);
    }

    public function test_future_date_in_user_timezone_is_rejected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 14:00:00'));

        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();
        $user->timezone = 'America/Mexico_City';
        $user->save();
        $this->actingAsUser($user);

        $tomorrowInMexico = Carbon::now('America/Mexico_City')->addDay()->toDateString();

        $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
            'date' => $tomorrowInMexico,
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('fieldErrors.date.0', 'The date field must not be in the future.');

        Carbon::setTestNow(null);
    }
}
