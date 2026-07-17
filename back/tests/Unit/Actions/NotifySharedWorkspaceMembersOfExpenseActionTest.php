<?php

namespace Tests\Unit\Actions;

use App\Actions\NotifySharedWorkspaceMembersOfExpenseAction;
use App\Models\Category;
use App\Models\Expense;
use App\Models\PushDevice;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ExpenseAddedToSharedWorkspaceNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class NotifySharedWorkspaceMembersOfExpenseActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    private function makeUser(string $name): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->assignRole('user');

        return $user;
    }

    private function makeSharedWorkspace(User $owner, User $memberB, User $memberC): Workspace
    {
        $workspace = Workspace::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Casa compartida',
        ]);

        $workspace->members()->attach($owner->id, ['role' => 'owner']);
        $workspace->members()->attach($memberB->id, ['role' => 'admin']);
        $workspace->members()->attach($memberC->id, ['role' => 'member']);

        return $workspace;
    }

    private function makeCategory(User $owner, Workspace $workspace): Category
    {
        $category = Category::factory()->forUser($owner)->create();
        $category->workspaces()->attach($workspace->id);

        return $category;
    }

    public function test_shared_workspace_notifies_other_members_and_excludes_author(): void
    {
        Notification::fake();

        $owner = $this->makeUser('Alice');
        $memberB = $this->makeUser('Bob');
        $memberC = $this->makeUser('Carol');
        $workspace = $this->makeSharedWorkspace($owner, $memberB, $memberC);
        $category = $this->makeCategory($owner, $workspace);

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'description' => 'Compras del súper',
        ]);

        app(NotifySharedWorkspaceMembersOfExpenseAction::class)->execute($expense);

        // Author (Alice) must NOT receive the notification
        Notification::assertNotSentTo($owner, ExpenseAddedToSharedWorkspaceNotification::class);

        // Other members DO receive it
        Notification::assertSentTo($memberB, ExpenseAddedToSharedWorkspaceNotification::class);
        Notification::assertSentTo($memberC, ExpenseAddedToSharedWorkspaceNotification::class);
    }

    public function test_shared_workspace_notification_is_sent_once_per_member(): void
    {
        Notification::fake();

        $owner = $this->makeUser('Alice');
        $memberB = $this->makeUser('Bob');
        $memberC = $this->makeUser('Carol');
        $workspace = $this->makeSharedWorkspace($owner, $memberB, $memberC);
        $category = $this->makeCategory($owner, $workspace);

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
        ]);

        app(NotifySharedWorkspaceMembersOfExpenseAction::class)->execute($expense);

        Notification::assertSentToTimes($memberB, ExpenseAddedToSharedWorkspaceNotification::class, 1);
        Notification::assertSentToTimes($memberC, ExpenseAddedToSharedWorkspaceNotification::class, 1);
    }

    public function test_personal_workspace_with_one_member_does_not_notify_anyone(): void
    {
        Notification::fake();

        $owner = $this->makeUser('Solo');
        $workspace = Workspace::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Mi espacio personal',
        ]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);
        $category = $this->makeCategory($owner, $workspace);

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
        ]);

        app(NotifySharedWorkspaceMembersOfExpenseAction::class)->execute($expense);

        Notification::assertNothingSent();
    }

    public function test_shared_workspace_without_fcm_tokens_still_persists_in_database(): void
    {
        Notification::fake();

        $owner = $this->makeUser('Alice');
        $memberB = $this->makeUser('Bob');
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);
        $workspace->members()->attach($memberB->id, ['role' => 'admin']);
        $category = $this->makeCategory($owner, $workspace);

        // Member has a revoked device only — should not be eligible for FCM push,
        // but the in-app (database) channel must still fire.
        PushDevice::factory()->create([
            'user_id' => $memberB->id,
            'fcm_token' => 'token-revoked',
            'notification_permission' => 'granted',
            'revoked_at' => now(),
        ]);

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
        ]);

        app(NotifySharedWorkspaceMembersOfExpenseAction::class)->execute($expense);

        // In-app notification must still be sent (database channel).
        Notification::assertSentTo(
            $memberB,
            ExpenseAddedToSharedWorkspaceNotification::class,
            function (ExpenseAddedToSharedWorkspaceNotification $notification) use ($memberB): bool {
                $channels = $notification->via($memberB);

                return in_array('database', $channels, true)
                    && ! in_array(\NotificationChannels\Fcm\FcmChannel::class, $channels, true);
            }
        );
    }

    public function test_shared_workspace_with_active_fcm_token_sends_via_fcm_channel(): void
    {
        Notification::fake();

        $owner = $this->makeUser('Alice');
        $memberB = $this->makeUser('Bob');
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);
        $workspace->members()->attach($memberB->id, ['role' => 'admin']);
        $category = $this->makeCategory($owner, $workspace);

        PushDevice::factory()->create([
            'user_id' => $memberB->id,
            'fcm_token' => 'token-granted-active',
            'notification_permission' => 'granted',
            'revoked_at' => null,
        ]);

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
        ]);

        app(NotifySharedWorkspaceMembersOfExpenseAction::class)->execute($expense);

        Notification::assertSentTo(
            $memberB,
            ExpenseAddedToSharedWorkspaceNotification::class,
            function (ExpenseAddedToSharedWorkspaceNotification $notification) use ($memberB): bool {
                $channels = $notification->via($memberB);

                return in_array('database', $channels, true)
                    && in_array(\NotificationChannels\Fcm\FcmChannel::class, $channels, true);
            }
        );
    }

    public function test_to_database_returns_expected_shape(): void
    {
        $owner = $this->makeUser('Alice');
        $memberB = $this->makeUser('Bob');
        $workspace = $this->makeSharedWorkspace($owner, $memberB, $this->makeUser('Carol'));
        $category = $this->makeCategory($owner, $workspace);

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'description' => 'Cena con amigos',
            'amount' => '42.50',
        ]);

        $notification = new ExpenseAddedToSharedWorkspaceNotification($expense->fresh(['user', 'workspace']));
        $payload = $notification->toDatabase($memberB);

        $this->assertSame(ExpenseAddedToSharedWorkspaceNotification::TYPE, $payload['type']);
        $this->assertSame($expense->id, $payload['expense_id']);
        $this->assertSame($workspace->id, $payload['workspace_id']);
        $this->assertSame($owner->id, $payload['user_id']);
        $this->assertSame('Cena con amigos', $payload['description']);
        $this->assertArrayHasKey('title', $payload);
        $this->assertStringContainsString('Alice', $payload['title']);
        $this->assertStringContainsString('Casa compartida', $payload['title']);
    }

    public function test_to_fcm_returns_expected_data_shape(): void
    {
        $owner = $this->makeUser('Alice');
        $memberB = $this->makeUser('Bob');
        $workspace = $this->makeSharedWorkspace($owner, $memberB, $this->makeUser('Carol'));
        $category = $this->makeCategory($owner, $workspace);

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'description' => 'Cena con amigos',
            'amount' => '42.50',
        ]);

        $notification = new ExpenseAddedToSharedWorkspaceNotification($expense->fresh(['user', 'workspace']));
        $message = $notification->toFcm($memberB);

        $payload = $message->toArray();

        $this->assertSame(ExpenseAddedToSharedWorkspaceNotification::TYPE, $payload['data']['type']);
        $this->assertSame((string) $expense->id, (string) $payload['data']['expense_id']);
        $this->assertSame($workspace->id, $payload['data']['workspace_id']);
        $this->assertSame($owner->id, $payload['data']['user_id']);

        // Notification envelope: title + body are rendered with placeholders.
        $this->assertArrayHasKey('notification', $payload);
        $this->assertStringContainsString('Alice', (string) $payload['notification']['title']);
        $this->assertStringContainsString('Casa compartida', (string) $payload['notification']['title']);
        $this->assertSame('Cena con amigos', (string) $payload['notification']['body']);
    }

    public function test_title_renders_in_spanish_and_english_with_placeholders(): void
    {
        $owner = $this->makeUser('Alice');
        $memberB = $this->makeUser('Bob');
        $workspace = $this->makeSharedWorkspace($owner, $memberB, $this->makeUser('Carol'));
        $category = $this->makeCategory($owner, $workspace);

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'description' => 'Café rápido',
        ]);

        $notification = new ExpenseAddedToSharedWorkspaceNotification($expense->fresh(['user', 'workspace']));

        $previousLocale = app()->getLocale();

        try {
            app()->setLocale('es');
            $esTitle = $notification->toDatabase($memberB)['title'];

            app()->setLocale('en');
            $enTitle = $notification->toDatabase($memberB)['title'];
        } finally {
            app()->setLocale($previousLocale);
        }

        $this->assertSame(
            'El usuario Alice agregó un gasto en el workspace Casa compartida',
            $esTitle
        );
        $this->assertSame(
            'User Alice added an expense in the workspace Casa compartida',
            $enTitle
        );
    }

    public function test_notification_is_dispatched_synchronously_and_not_queued(): void
    {
        // The notification is intentionally synchronous: there is no ShouldQueue
        // contract on it. This guards the contract so a future change does not
        // silently enqueue it (which would change timing assumptions made by
        // ExpenseController::store).
        $this->assertNotInstanceOf(
            ShouldQueue::class,
            new ExpenseAddedToSharedWorkspaceNotification(
                Expense::factory()->make([
                    'id' => '00000000-0000-0000-0000-000000000000',
                    'user_id' => '00000000-0000-0000-0000-000000000000',
                    'workspace_id' => '00000000-0000-0000-0000-000000000000',
                ])
            )
        );

        Queue::fake();

        $owner = $this->makeUser('Alice');
        $memberB = $this->makeUser('Bob');
        $workspace = $this->makeSharedWorkspace($owner, $memberB, $this->makeUser('Carol'));
        $category = $this->makeCategory($owner, $workspace);

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
        ]);

        app(NotifySharedWorkspaceMembersOfExpenseAction::class)->execute($expense);

        Queue::assertNothingPushed();
    }

    public function test_continues_notifying_other_members_when_notify_throws(): void
    {
        Log::spy();

        $owner = $this->makeUser('Alice');
        $memberB = $this->makeUser('Bob');
        $memberC = $this->makeUser('Carol');
        $workspace = $this->makeSharedWorkspace($owner, $memberB, $memberC);
        $category = $this->makeCategory($owner, $workspace);

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
        ]);

        $expense->load('workspace.members');

        $failingMember = Mockery::mock($memberB)->makePartial();
        $failingMember->shouldReceive('notify')
            ->once()
            ->andThrow(new RuntimeException('Firebase project [app] not configured.'));

        $successfulMember = Mockery::mock($memberC)->makePartial();
        $successfulMember->shouldReceive('notify')->once();

        $expense->workspace->setRelation('members', collect([$owner, $failingMember, $successfulMember]));

        app(NotifySharedWorkspaceMembersOfExpenseAction::class)->execute($expense);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'Failed to notify workspace member of new expense.',
                [
                    'user_id' => $memberB->id,
                    'expense_id' => $expense->id,
                    'exception' => 'Firebase project [app] not configured.',
                ]
            );
    }
}
