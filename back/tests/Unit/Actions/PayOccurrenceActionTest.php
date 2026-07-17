<?php

namespace Tests\Unit\Actions;

use App\Contracts\PayOccurrenceActionInterface;
use App\Contracts\RegisterExpenseActionInterface;
use App\Models\Card;
use App\Models\Category;
use App\Models\Expense;
use App\Models\FixedExpense;
use App\Models\FixedExpenseOccurrence;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ExpenseAddedToSharedWorkspaceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class PayOccurrenceActionTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    /**
     * Build a workspace with an active category linked and a card, then return
     * a FixedExpense and an Occurrence anchored to that workspace.
     *
     * @return array{
     *   user: User,
     *   workspace: Workspace,
     *   category: Category,
     *   card: Card,
     *   fixedExpense: FixedExpense,
     *   occurrence: FixedExpenseOccurrence,
     * }
     */
    private function buildPendingOccurrence(string $status = 'pending'): array
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace();

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $occurrence = FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'status' => $status,
            'suggested_amount' => '500.00',
        ]);

        return compact('user', 'workspace', 'category', 'card', 'fixedExpense', 'occurrence');
    }

    public function test_pending_occurrence_is_paid_and_creates_expense(): void
    {
        Notification::fake();

        $ctx = $this->buildPendingOccurrence('pending');
        /** @var PayOccurrenceAction $action */
        $action = app(PayOccurrenceActionInterface::class);

        $expense = $action->execute(
            $ctx['user'],
            $ctx['occurrence'],
            [
                'amount' => '450.00',
                'payment_type' => 'card',
                'payment_instrument_id' => $ctx['card']->id,
                'paid_at' => now()->toDateString(),
            ]
        );

        // Expense persisted with the expected fields.
        $this->assertInstanceOf(Expense::class, $expense);
        $this->assertSame('450.00', (string) $expense->amount);
        $this->assertSame($ctx['workspace']->id, $expense->workspace_id);
        $this->assertSame($ctx['user']->id, $expense->user_id);
        $this->assertSame($ctx['category']->id, $expense->category_id);
        $this->assertSame('card', $expense->payment_type);
        $this->assertSame($ctx['card']->id, $expense->payment_instrument_id);
        $this->assertSame($ctx['fixedExpense']->id, $expense->fixed_expense_id);
        // paid_by_user_id defaults to the authenticated user when not
        // provided in the payload.
        $this->assertSame($ctx['user']->id, $expense->paid_by_user_id);

        // Occurrence is marked as paid with the correct payment metadata.
        $ctx['occurrence']->refresh();
        $this->assertSame('paid', $ctx['occurrence']->status);
        $this->assertSame('450.00', (string) $ctx['occurrence']->actual_amount);
        $this->assertSame('card', $ctx['occurrence']->payment_type);
        $this->assertSame($ctx['card']->id, $ctx['occurrence']->payment_instrument_id);
        $this->assertNotNull($ctx['occurrence']->paid_at);
        $this->assertSame($expense->id, $ctx['occurrence']->expense_id);
    }

    public function test_paid_by_user_id_is_overridden_when_passed_in_payload(): void
    {
        Notification::fake();

        $ctx = $this->buildPendingOccurrence('pending');
        $payer = User::factory()->create();
        $ctx['workspace']->members()->attach($payer->id, ['role' => 'guest']);

        /** @var PayOccurrenceAction $action */
        $action = app(PayOccurrenceActionInterface::class);

        $expense = $action->execute(
            $ctx['user'],
            $ctx['occurrence'],
            [
                'amount' => '500.00',
                'payment_type' => 'card',
                'payment_instrument_id' => $ctx['card']->id,
                'paid_at' => now()->toDateString(),
                'paid_by_user_id' => $payer->id,
            ]
        );

        $this->assertSame($payer->id, $expense->paid_by_user_id);
    }

    public function test_paying_occurrence_without_paid_by_user_id_sets_to_authenticated_user(): void
    {
        Notification::fake();

        $ctx = $this->buildPendingOccurrence('pending');

        /** @var PayOccurrenceAction $action */
        $action = app(PayOccurrenceActionInterface::class);

        $expense = $action->execute(
            $ctx['user'],
            $ctx['occurrence'],
            [
                'amount' => '500.00',
                'payment_type' => 'card',
                'payment_instrument_id' => $ctx['card']->id,
                'paid_at' => now()->toDateString(),
            ]
        );

        $this->assertSame($ctx['user']->id, $expense->paid_by_user_id);
    }

    public function test_paying_occurrence_with_paid_by_user_id_uses_provided_value(): void
    {
        Notification::fake();

        $ctx = $this->buildPendingOccurrence('pending');
        $payer = User::factory()->create();
        $ctx['workspace']->members()->attach($payer->id, ['role' => 'guest']);

        /** @var PayOccurrenceAction $action */
        $action = app(PayOccurrenceActionInterface::class);

        $expense = $action->execute(
            $ctx['user'],
            $ctx['occurrence'],
            [
                'amount' => '500.00',
                'payment_type' => 'card',
                'payment_instrument_id' => $ctx['card']->id,
                'paid_at' => now()->toDateString(),
                'paid_by_user_id' => $payer->id,
            ]
        );

        $this->assertSame($payer->id, $expense->paid_by_user_id);
    }

    public function test_already_paid_occurrence_throws_validation_exception(): void
    {
        Notification::fake();

        $ctx = $this->buildPendingOccurrence('paid');
        $ctx['occurrence']->update(['paid_at' => now()]);

        /** @var PayOccurrenceAction $action */
        $action = app(PayOccurrenceActionInterface::class);

        $this->expectException(ValidationException::class);

        try {
            $action->execute(
                $ctx['user'],
                $ctx['occurrence'],
                [
                    'amount' => '450.00',
                    'payment_type' => 'card',
                    'payment_instrument_id' => $ctx['card']->id,
                    'paid_at' => now()->toDateString(),
                ]
            );
        } finally {
            // No duplicate expense is created when the action rejects.
            $this->assertSame(0, Expense::query()->where('fixed_expense_id', $ctx['fixedExpense']->id)->count());
            $this->assertSame('paid', $ctx['occurrence']->fresh()->status);
        }
    }

    public function test_fixed_expense_with_non_valid_category_fails_with_validation_exception(): void
    {
        Notification::fake();

        $ctx = $this->buildPendingOccurrence('pending');

        // Mark the category as inactive in the workspace pivot so it is no
        // longer "transactionally valid" for the workspace.
        $ctx['category']->workspaces()->updateExistingPivot($ctx['workspace']->id, [
            'is_active' => false,
        ]);

        /** @var PayOccurrenceAction $action */
        $action = app(PayOccurrenceActionInterface::class);

        try {
            $action->execute(
                $ctx['user'],
                $ctx['occurrence'],
                [
                    'amount' => '450.00',
                    'payment_type' => 'card',
                    'payment_instrument_id' => $ctx['card']->id,
                    'paid_at' => now()->toDateString(),
                ]
            );
            $this->fail('Expected ValidationException to be thrown.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('category_id', $exception->errors());
        }

        // No expense was created and the occurrence remains in its original state.
        $this->assertSame(0, Expense::query()->count());
        $ctx['occurrence']->refresh();
        $this->assertSame('pending', $ctx['occurrence']->status);
        $this->assertNull($ctx['occurrence']->expense_id);
    }

    public function test_fixed_expense_with_category_unlinked_from_workspace_fails(): void
    {
        Notification::fake();

        $ctx = $this->buildPendingOccurrence('pending');

        // Unlink the category from the workspace entirely.
        $ctx['category']->workspaces()->detach($ctx['workspace']->id);

        /** @var PayOccurrenceAction $action */
        $action = app(PayOccurrenceActionInterface::class);

        $this->expectException(ValidationException::class);

        try {
            $action->execute(
                $ctx['user'],
                $ctx['occurrence'],
                [
                    'amount' => '450.00',
                    'payment_type' => 'card',
                    'payment_instrument_id' => $ctx['card']->id,
                    'paid_at' => now()->toDateString(),
                ]
            );
        } finally {
            $this->assertSame(0, Expense::query()->count());
            $this->assertSame('pending', $ctx['occurrence']->fresh()->status);
        }
    }

    public function test_is_atomic_when_expense_creation_fails(): void
    {
        Notification::fake();

        $ctx = $this->buildPendingOccurrence('pending');

        // Swap the RegisterExpenseActionInterface for one that creates the
        // expense but then throws, so the transaction should roll back.
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

                throw new RuntimeException('Simulated failure after expense creation');
            }
        });

        /** @var PayOccurrenceAction $action */
        $action = app(PayOccurrenceActionInterface::class);

        try {
            $action->execute(
                $ctx['user'],
                $ctx['occurrence'],
                [
                    'amount' => '450.00',
                    'payment_type' => 'card',
                    'payment_instrument_id' => $ctx['card']->id,
                    'paid_at' => now()->toDateString(),
                ]
            );
            $this->fail('Expected RuntimeException to be thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated failure after expense creation', $exception->getMessage());
        }

        // The transaction must have rolled back: no expense and the occurrence
        // is still pending without an expense_id.
        $this->assertSame(0, Expense::query()->count());
        $ctx['occurrence']->refresh();
        $this->assertSame('pending', $ctx['occurrence']->status);
        $this->assertNull($ctx['occurrence']->expense_id);
        $this->assertNull($ctx['occurrence']->actual_amount);
        $this->assertNull($ctx['occurrence']->paid_at);
    }

    public function test_overdue_occurrence_can_be_paid(): void
    {
        Notification::fake();

        $ctx = $this->buildPendingOccurrence('overdue');
        $ctx['occurrence']->update(['due_date' => now()->subDays(5)->toDateString()]);

        /** @var PayOccurrenceAction $action */
        $action = app(PayOccurrenceActionInterface::class);

        $expense = $action->execute(
            $ctx['user'],
            $ctx['occurrence'],
            [
                'amount' => '500.00',
                'payment_type' => 'cash',
                'paid_at' => now()->toDateString(),
            ]
        );

        $this->assertInstanceOf(Expense::class, $expense);
        $this->assertSame('cash', $expense->payment_type);
        $this->assertNull($expense->payment_instrument_id);
        $this->assertSame('500.00', (string) $expense->amount);

        $ctx['occurrence']->refresh();
        $this->assertSame('paid', $ctx['occurrence']->status);
        $this->assertSame('cash', $ctx['occurrence']->payment_type);
    }

    public function test_action_does_not_dispatch_shared_workspace_notification_by_itself(): void
    {
        Notification::fake();

        // Build a shared workspace so the notification action would have
        // someone to notify if it were called.
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'type' => 'familiar']);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);
        $workspace->members()->attach($member->id, ['role' => 'admin']);

        $category = Category::factory()->forUser($owner)->create();
        $category->workspaces()->attach($workspace->id);
        $card = Card::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
        $card->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $occurrence = FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'status' => 'pending',
        ]);

        /** @var PayOccurrenceAction $action */
        $action = app(PayOccurrenceActionInterface::class);

        $action->execute(
            $owner,
            $occurrence,
            [
                'amount' => '100.00',
                'payment_type' => 'card',
                'payment_instrument_id' => $card->id,
                'paid_at' => now()->toDateString(),
            ]
        );

        // The notification is the controller's responsibility (see
        // ExpenseController::store). The action itself must stay isolated and
        // not push notifications to other workspace members.
        Notification::assertNotSentTo($member, ExpenseAddedToSharedWorkspaceNotification::class);
    }

    public function test_register_expense_dependency_is_called_with_expected_arguments(): void
    {
        Notification::fake();

        $ctx = $this->buildPendingOccurrence('pending');

        // Replace the registered expense action with a spy that records the
        // arguments and forwards to the real implementation. The container
        // resolves the real action via a new instance on demand.
        $captured = ['data' => null, 'user' => null, 'workspace' => null];

        $this->app->bind(RegisterExpenseActionInterface::class, function () use (&$captured) {
            return new class($captured) implements RegisterExpenseActionInterface
            {
                /** @param array<string, mixed> $captured */
                public function __construct(private array &$captured) {}

                public function execute(User $user, Workspace $workspace, array $data): Expense
                {
                    $this->captured['user'] = $user;
                    $this->captured['workspace'] = $workspace;
                    $this->captured['data'] = $data;

                    return Expense::query()->create([
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
                }
            };
        });

        /** @var PayOccurrenceAction $action */
        $action = app(PayOccurrenceActionInterface::class);

        $action->execute(
            $ctx['user'],
            $ctx['occurrence'],
            [
                'amount' => '450.00',
                'payment_type' => 'card',
                'payment_instrument_id' => $ctx['card']->id,
                'paid_at' => now()->toDateString(),
            ]
        );

        $this->assertSame($ctx['user']->id, $captured['user']->id);
        $this->assertSame($ctx['workspace']->id, $captured['workspace']->id);
        $this->assertSame('450.00', (string) $captured['data']['amount']);
        $this->assertSame($ctx['fixedExpense']->id, $captured['data']['fixed_expense_id']);
        $this->assertSame($ctx['category']->id, $captured['data']['category_id']);
        $this->assertSame($ctx['workspace']->id, $captured['data']['workspace_id']);
        $this->assertSame('card', $captured['data']['payment_type']);
        $this->assertSame($ctx['card']->id, $captured['data']['payment_instrument_id']);
        $this->assertSame($ctx['fixedExpense']->description, $captured['data']['description']);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
}
