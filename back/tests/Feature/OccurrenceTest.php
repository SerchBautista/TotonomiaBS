<?php

namespace Tests\Feature;

use App\Contracts\RegisterExpenseActionInterface;
use App\Models\Expense;
use App\Models\FixedExpense;
use App\Models\FixedExpenseOccurrence;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ExpenseAddedToSharedWorkspaceNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class OccurrenceTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_lists_only_pending_and_overdue_occurrences(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'status' => 'pending',
        ]);
        FixedExpenseOccurrence::factory()->overdue()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'due_date' => now()->subDays(5)->toDateString(),
        ]);
        FixedExpenseOccurrence::factory()->paid()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'due_date' => now()->subDays(35)->toDateString(),
        ]);

        $this->actingAsUser($user);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/occurrences")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_empty_list_returns_200(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/occurrences")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_pay_occurrence_creates_expense_and_marks_paid(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $occurrence = FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'status' => 'pending',
            'suggested_amount' => '500.00',
        ]);

        $this->actingAsUser($user);

        $this->postJson("/api/v1/occurrences/{$occurrence->id}/pay", [
            'amount' => '450.00',
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'paid_at' => now()->toDateString(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '450.00');

        $this->assertDatabaseHas('fixed_expense_occurrences', [
            'id' => $occurrence->id,
            'status' => 'paid',
            'actual_amount' => '450.00',
        ]);

        $this->assertDatabaseHas('expenses', [
            'fixed_expense_id' => $fixedExpense->id,
            'amount' => '450.00',
        ]);
    }

    public function test_pay_overdue_occurrence_succeeds(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $occurrence = FixedExpenseOccurrence::factory()->overdue()->create([
            'fixed_expense_id' => $fixedExpense->id,
        ]);

        $this->actingAsUser($user);

        $this->postJson("/api/v1/occurrences/{$occurrence->id}/pay", [
            'amount' => '500.00',
            'payment_type' => 'cash',
            'paid_at' => now()->toDateString(),
        ])->assertCreated();

        $this->assertDatabaseHas('fixed_expense_occurrences', [
            'id' => $occurrence->id,
            'status' => 'paid',
        ]);
    }

    public function test_paying_already_paid_occurrence_returns_422(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $occurrence = FixedExpenseOccurrence::factory()->paid()->create([
            'fixed_expense_id' => $fixedExpense->id,
        ]);

        $this->actingAsUser($user);

        $this->postJson("/api/v1/occurrences/{$occurrence->id}/pay", [
            'amount' => '500.00',
            'payment_type' => 'cash',
            'paid_at' => now()->toDateString(),
        ])->assertUnprocessable();
    }

    public function test_cannot_pay_occurrence_with_future_date(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $occurrence = FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'status' => 'pending',
        ]);

        $this->actingAsUser($user);

        $this->postJson("/api/v1/occurrences/{$occurrence->id}/pay", [
            'amount' => '100.00',
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'paid_at' => '2099-12-31',
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('fieldErrors.paid_at.0', 'The payment date field must not be in the future.');
    }

    public function test_cannot_pay_occurrence_with_future_date_in_user_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-15 14:00:00'));

        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();
        $user->timezone = 'America/Mexico_City';
        $user->save();

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $occurrence = FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'status' => 'pending',
        ]);

        $this->actingAsUser($user);

        $tomorrowInMexico = Carbon::now('America/Mexico_City')->addDay()->toDateString();

        $this->postJson("/api/v1/occurrences/{$occurrence->id}/pay", [
            'amount' => '100.00',
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'paid_at' => $tomorrowInMexico,
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('fieldErrors.paid_at.0', 'The payment date field must not be in the future.');

        Carbon::setTestNow(null);
    }

    public function test_pay_occurrence_stores_paid_by_user_id(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $payer = User::factory()->create();
        $workspace->members()->attach($payer->id, ['role' => 'guest']);

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $occurrence = FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'status' => 'pending',
            'suggested_amount' => '100.00',
        ]);

        $this->actingAsUser($user);

        $this->postJson("/api/v1/occurrences/{$occurrence->id}/pay", [
            'amount' => '100.00',
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'paid_at' => now()->toDateString(),
            'paid_by_user_id' => $payer->id,
        ])->assertCreated();

        $this->assertDatabaseHas('expenses', [
            'fixed_expense_id' => $fixedExpense->id,
            'paid_by_user_id' => $payer->id,
        ]);
    }

    public function test_paying_occurrence_via_api_sets_paid_by_user_id_to_current_user(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $occurrence = FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'status' => 'pending',
            'suggested_amount' => '100.00',
        ]);

        $this->actingAsUser($user);

        $this->postJson("/api/v1/occurrences/{$occurrence->id}/pay", [
            'amount' => '100.00',
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'paid_at' => now()->toDateString(),
        ])->assertCreated();

        $this->assertDatabaseHas('expenses', [
            'fixed_expense_id' => $fixedExpense->id,
            'paid_by_user_id' => $user->id,
        ]);
    }

    public function test_pay_occurrence_fails_when_fixed_expense_category_is_not_transactionally_valid(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace(['type' => 'familiar']);

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $occurrence = FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'status' => 'pending',
        ]);

        $cat->workspaces()->updateExistingPivot($workspace->id, [
            'is_active' => false,
        ]);

        $this->actingAsUser($user);

        $this->postJson("/api/v1/occurrences/{$occurrence->id}/pay", [
            'amount' => '100.00',
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'paid_at' => now()->toDateString(),
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('fieldErrors.category_id.0', 'The selected category is invalid for the current workspace.');

        $this->assertDatabaseMissing('expenses', [
            'fixed_expense_id' => $fixedExpense->id,
        ]);

        $this->assertDatabaseHas('fixed_expense_occurrences', [
            'id' => $occurrence->id,
            'status' => 'pending',
            'expense_id' => null,
        ]);
    }

    public function test_pay_occurrence_is_atomic_when_expense_registration_fails_after_creating_expense(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $occurrence = FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'status' => 'pending',
        ]);

        $this->app->bind(RegisterExpenseActionInterface::class, fn () => new class implements RegisterExpenseActionInterface
        {
            public function execute(User $user, Workspace $workspace, array $data): Expense
            {
                Expense::query()->create([
                    'workspace_id' => $workspace->id,
                    'user_id' => $user->id,
                    'category_id' => $data['category_id'],
                    'payment_type' => $data['payment_type'],
                    'payment_instrument_id' => $data['payment_instrument_id'] ?? null,
                    'fixed_expense_id' => $data['fixed_expense_id'] ?? null,
                    'amount' => $data['amount'],
                    'date' => $data['date'],
                    'description' => $data['description'] ?? null,
                    'paid_by_user_id' => $data['paid_by_user_id'] ?? null,
                ]);

                throw new \RuntimeException('Simulated failure after expense creation');
            }
        });

        $this->actingAsUser($user);

        $this->postJson("/api/v1/occurrences/{$occurrence->id}/pay", [
            'amount' => '100.00',
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'paid_at' => now()->toDateString(),
        ])->assertStatus(500);

        $this->assertDatabaseMissing('expenses', [
            'fixed_expense_id' => $fixedExpense->id,
            'amount' => '100.00',
        ]);

        $this->assertDatabaseHas('fixed_expense_occurrences', [
            'id' => $occurrence->id,
            'status' => 'pending',
            'expense_id' => null,
        ]);
    }

    public function test_paying_occurrence_notifies_shared_workspace_members(): void
    {
        Notification::fake();

        ['user' => $author, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace(['type' => 'familiar']);

        $memberB = User::factory()->create(['name' => 'Bob']);
        $memberB->assignRole('user');
        $workspace->members()->attach($memberB->id, ['role' => 'admin']);

        $memberC = User::factory()->create(['name' => 'Carol']);
        $memberC->assignRole('user');
        $workspace->members()->attach($memberC->id, ['role' => 'member']);

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $author->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $occurrence = FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'status' => 'pending',
            'suggested_amount' => '500.00',
        ]);

        $this->actingAsUser($author);

        $this->postJson("/api/v1/occurrences/{$occurrence->id}/pay", [
            'amount' => '450.00',
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'paid_at' => now()->toDateString(),
        ])->assertCreated();

        Notification::assertSentTo($memberB, ExpenseAddedToSharedWorkspaceNotification::class);
        Notification::assertSentTo($memberC, ExpenseAddedToSharedWorkspaceNotification::class);
    }

    public function test_paying_occurrence_does_not_notify_in_personal_workspace(): void
    {
        Notification::fake();

        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace(['type' => 'personal']);

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $occurrence = FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'status' => 'pending',
            'suggested_amount' => '100.00',
        ]);

        $this->actingAsUser($user);

        $this->postJson("/api/v1/occurrences/{$occurrence->id}/pay", [
            'amount' => '100.00',
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'paid_at' => now()->toDateString(),
        ])->assertCreated();

        Notification::assertNothingSent();
    }

    public function test_paying_occurrence_does_not_notify_author(): void
    {
        Notification::fake();

        ['user' => $author, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace(['type' => 'familiar']);

        $memberB = User::factory()->create(['name' => 'Bob']);
        $memberB->assignRole('user');
        $workspace->members()->attach($memberB->id, ['role' => 'admin']);

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $author->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $occurrence = FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'status' => 'pending',
            'suggested_amount' => '100.00',
        ]);

        $this->actingAsUser($author);

        $this->postJson("/api/v1/occurrences/{$occurrence->id}/pay", [
            'amount' => '100.00',
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'paid_at' => now()->toDateString(),
        ])->assertCreated();

        Notification::assertNotSentTo($author, ExpenseAddedToSharedWorkspaceNotification::class);
        Notification::assertSentTo($memberB, ExpenseAddedToSharedWorkspaceNotification::class);
    }
}
