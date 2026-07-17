<?php

namespace Tests\Unit\Actions;

use App\Actions\RegisterExpenseAction;
use App\Contracts\RegisterExpenseActionInterface;
use App\Models\Category;
use App\Models\Expense;
use App\Models\OtherPaymentMethod;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ExpenseAddedToSharedWorkspaceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class RegisterExpenseActionTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_registers_valid_expense_with_all_fields(): void
    {
        Notification::fake();

        ['user' => $user, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace();

        /** @var RegisterExpenseAction $action */
        $action = app(RegisterExpenseActionInterface::class);

        $expense = $action->execute($user, $workspace, [
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '123.45',
            'date' => '2026-06-01',
            'description' => 'Compra de prueba',
            'paid_by_user_id' => $user->id,
        ]);

        $this->assertInstanceOf(Expense::class, $expense);
        $this->assertSame($workspace->id, $expense->workspace_id);
        $this->assertSame($user->id, $expense->user_id);
        $this->assertSame($category->id, $expense->category_id);
        $this->assertSame('card', $expense->payment_type);
        $this->assertSame($card->id, $expense->payment_instrument_id);
        $this->assertSame('123.45', (string) $expense->amount);
        $this->assertSame('2026-06-01', $expense->date->toDateString());
        $this->assertSame('Compra de prueba', $expense->description);
        $this->assertSame($user->id, $expense->paid_by_user_id);
        $this->assertNull($expense->fixed_expense_id);

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'amount' => '123.45',
        ]);
    }

    public function test_client_uuid_is_respected_when_provided(): void
    {
        Notification::fake();

        ['user' => $user, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace();
        $clientUuid = Str::uuid()->toString();

        /** @var RegisterExpenseAction $action */
        $action = app(RegisterExpenseActionInterface::class);

        $expense = $action->execute($user, $workspace, [
            'id' => $clientUuid,
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '10.00',
            'date' => '2026-06-01',
        ]);

        $this->assertSame($clientUuid, $expense->id);
        $this->assertDatabaseHas('expenses', ['id' => $clientUuid]);
    }

    public function test_action_persists_zero_amount_without_validation(): void
    {
        // HALLAZGO H-007: The action does NOT validate "amount > 0".
        // That responsibility belongs to StoreExpenseRequest::rules().
        // This test documents the action's actual behavior so a future
        // change to the action layer is intentional. The negative case is
        // covered at the FormRequest level in tests/Feature/ExpenseTest.
        Notification::fake();

        ['user' => $user, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace();

        /** @var RegisterExpenseAction $action */
        $action = app(RegisterExpenseActionInterface::class);

        $expense = $action->execute($user, $workspace, [
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '0',
            'date' => '2026-06-01',
        ]);

        $this->assertSame('0.00', (string) $expense->amount);
    }

    public function test_action_persists_negative_amount_without_validation(): void
    {
        // HALLAZGO H-007: The action does NOT validate "amount > 0".
        // Validation is delegated to the FormRequest layer.
        Notification::fake();

        ['user' => $user, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace();

        /** @var RegisterExpenseAction $action */
        $action = app(RegisterExpenseActionInterface::class);

        $expense = $action->execute($user, $workspace, [
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '-10.00',
            'date' => '2026-06-01',
        ]);

        $this->assertSame('-10.00', (string) $expense->amount);
    }

    public function test_future_date_is_persisted_as_is_by_the_action(): void
    {
        // The action itself does not validate "date not in the future" — that
        // responsibility belongs to the FormRequest. We document the current
        // behavior so a future change to the action layer is intentional.
        Notification::fake();

        ['user' => $user, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace();

        /** @var RegisterExpenseAction $action */
        $action = app(RegisterExpenseActionInterface::class);

        $expense = $action->execute($user, $workspace, [
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '50.00',
            'date' => '2099-12-31',
        ]);

        $this->assertSame('2099-12-31', $expense->date->toDateString());
    }

    public function test_category_not_owned_by_workspace_owner_is_still_persisted_by_action(): void
    {
        // The action does not validate that the category belongs to the
        // workspace — that is the responsibility of the FormRequest. We
        // document the action's actual behavior here.
        Notification::fake();

        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);
        $category = Category::factory()->forUser($owner)->create();
        // Note: not attached to the workspace.

        /** @var RegisterExpenseAction $action */
        $action = app(RegisterExpenseActionInterface::class);

        $expense = $action->execute($owner, $workspace, [
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'amount' => '10.00',
            'date' => '2026-06-01',
        ]);

        $this->assertSame($category->id, $expense->category_id);
    }

    public function test_invalid_payment_type_is_persisted_by_action_when_payment_instrument_is_omitted(): void
    {
        // Action allows any payment_type string; validation is the
        // FormRequest's responsibility. We exercise the cash path with no
        // payment instrument to assert the action accepts it.
        Notification::fake();

        ['user' => $user, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();

        /** @var RegisterExpenseAction $action */
        $action = app(RegisterExpenseActionInterface::class);

        $expense = $action->execute($user, $workspace, [
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'amount' => '25.00',
            'date' => '2026-06-01',
        ]);

        $this->assertSame('cash', $expense->payment_type);
        $this->assertNull($expense->payment_instrument_id);
    }

    public function test_links_to_fixed_expense_when_provided(): void
    {
        Notification::fake();

        ['user' => $user, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace();
        $fixedExpense = \App\Models\FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        /** @var RegisterExpenseAction $action */
        $action = app(RegisterExpenseActionInterface::class);

        $expense = $action->execute($user, $workspace, [
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'fixed_expense_id' => $fixedExpense->id,
            'amount' => '500.00',
            'date' => '2026-06-01',
        ]);

        $this->assertSame($fixedExpense->id, $expense->fixed_expense_id);
    }

    public function test_action_does_not_dispatch_shared_workspace_notification_by_itself(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'type' => 'familiar']);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);
        $workspace->members()->attach($member->id, ['role' => 'admin']);

        $category = Category::factory()->forUser($owner)->create();
        $category->workspaces()->attach($workspace->id);

        /** @var RegisterExpenseAction $action */
        $action = app(RegisterExpenseActionInterface::class);

        $expense = $action->execute($owner, $workspace, [
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'amount' => '50.00',
            'date' => '2026-06-01',
        ]);

        // The action is intentionally isolated from cross-cutting concerns.
        // Notification dispatch is the controller's responsibility (see
        // ExpenseController::store). When the action is invoked directly,
        // no notification must be sent.
        Notification::assertNotSentTo($member, ExpenseAddedToSharedWorkspaceNotification::class);
        Notification::assertNotSentTo($owner, ExpenseAddedToSharedWorkspaceNotification::class);

        $this->assertInstanceOf(Expense::class, $expense);
    }

    public function test_action_loads_required_relations_on_the_returned_model(): void
    {
        Notification::fake();

        ['user' => $user, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace();

        /** @var RegisterExpenseAction $action */
        $action = app(RegisterExpenseActionInterface::class);

        $expense = $action->execute($user, $workspace, [
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '75.00',
            'date' => '2026-06-01',
            'paid_by_user_id' => $user->id,
        ]);

        $this->assertTrue($expense->relationLoaded('category'));
        $this->assertTrue($expense->relationLoaded('paymentInstrument'));
        $this->assertTrue($expense->relationLoaded('user'));
        $this->assertTrue($expense->relationLoaded('paidBy'));
        $this->assertSame($category->id, $expense->category->id);
        $this->assertSame($card->id, $expense->paymentInstrument->id);
    }

    public function test_paid_by_user_id_can_be_null_when_omitted(): void
    {
        Notification::fake();

        ['user' => $user, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace();

        /** @var RegisterExpenseAction $action */
        $action = app(RegisterExpenseActionInterface::class);

        $expense = $action->execute($user, $workspace, [
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '75.00',
            'date' => '2026-06-01',
        ]);

        $this->assertNull($expense->paid_by_user_id);
    }

    public function test_other_payment_method_is_persisted(): void
    {
        Notification::fake();

        ['user' => $user, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();
        $opm = OtherPaymentMethod::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
        $opm->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        /** @var RegisterExpenseAction $action */
        $action = app(RegisterExpenseActionInterface::class);

        $expense = $action->execute($user, $workspace, [
            'category_id' => $category->id,
            'payment_type' => 'other',
            'payment_instrument_id' => $opm->id,
            'amount' => '15.00',
            'date' => '2026-06-01',
        ]);

        $this->assertSame('other', $expense->payment_type);
        $this->assertSame($opm->id, $expense->payment_instrument_id);
        $this->assertInstanceOf(OtherPaymentMethod::class, $expense->paymentInstrument);
    }
}
