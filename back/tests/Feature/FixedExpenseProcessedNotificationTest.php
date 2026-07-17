<?php

namespace Tests\Feature;

use App\Actions\CreateOccurrenceAction;
use App\Actions\ProcessFixedExpensesAction;
use App\Models\Category;
use App\Models\FixedExpense;
use App\Models\FixedExpenseOccurrence;
use App\Models\PushDevice;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\FixedExpenseProcessedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Fcm\FcmChannel;
use Tests\TestCase;

class FixedExpenseProcessedNotificationTest extends TestCase
{
    use RefreshDatabase;

    private ProcessFixedExpensesAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);

        $this->action = new ProcessFixedExpensesAction(new CreateOccurrenceAction);
    }

    public function test_owner_receives_fixed_expense_processed_notification_via_cron(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $owner->assignRole('user');

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        $category = Category::factory()->forUser($owner)->create();
        $category->workspaces()->attach($workspace->id);

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
            'amount' => '99.00',
            'description' => 'Servicio de streaming',
            'frequency' => 'monthly',
            'next_due_date' => now()->toDateString(),
            'is_active' => true,
            'reminders_enabled' => true,
        ]);

        $this->action->execute();

        Notification::assertSentTo($owner, FixedExpenseProcessedNotification::class);
    }

    public function test_processed_notification_database_payload_contains_amount_category_and_due_date(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        $category = Category::factory()->forUser($owner)->create([
            'name' => 'Entretenimiento',
        ]);
        $category->workspaces()->attach($workspace->id);

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
            'amount' => '250.00',
            'description' => 'Netflix',
            'frequency' => 'monthly',
            'next_due_date' => '2026-07-15',
            'is_active' => true,
            'reminders_enabled' => true,
        ]);

        $occurrence = FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'due_date' => '2026-07-15',
            'status' => 'pending',
        ]);

        $notification = new FixedExpenseProcessedNotification($fixedExpense, $occurrence);
        $payload = $notification->toDatabase($owner);

        $this->assertSame('fixed_expense_processed', $payload['type']);
        $this->assertSame('Netflix', $payload['title']);
        $this->assertSame('250.00', (string) $payload['amount']);
        $this->assertSame('2026-07-15', $payload['due_date']);
        $this->assertSame($fixedExpense->id, $payload['fixed_expense_id']);
        $this->assertSame($occurrence->id, $payload['occurrence_id']);
        $this->assertSame($workspace->id, $payload['workspace_id']);
    }

    public function test_processed_notification_via_includes_fcm_channel_when_user_has_active_fcm_tokens(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        $category = Category::factory()->forUser($owner)->create();
        $category->workspaces()->attach($workspace->id);

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
            'amount' => '50.00',
            'description' => 'Test',
            'frequency' => 'monthly',
            'next_due_date' => now()->toDateString(),
            'is_active' => true,
            'reminders_enabled' => true,
        ]);

        $occurrence = FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'due_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        // Owner has an active, granted FCM device.
        PushDevice::factory()->create([
            'user_id' => $owner->id,
            'fcm_token' => 'active-token',
            'notification_permission' => 'granted',
            'revoked_at' => null,
        ]);

        $notification = new FixedExpenseProcessedNotification($fixedExpense, $occurrence);
        $channels = $notification->via($owner);

        $this->assertContains('database', $channels);
        $this->assertContains(FcmChannel::class, $channels);
    }

    public function test_processed_notification_via_omits_fcm_channel_when_user_has_no_active_fcm_tokens(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        $category = Category::factory()->forUser($owner)->create();
        $category->workspaces()->attach($workspace->id);

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
            'amount' => '50.00',
            'description' => 'Test',
            'frequency' => 'monthly',
            'next_due_date' => now()->toDateString(),
            'is_active' => true,
            'reminders_enabled' => true,
        ]);

        $occurrence = FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'due_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        // Owner has only a revoked device — no active tokens.
        PushDevice::factory()->create([
            'user_id' => $owner->id,
            'notification_permission' => 'granted',
            'revoked_at' => now(),
        ]);

        $notification = new FixedExpenseProcessedNotification($fixedExpense, $occurrence);
        $channels = $notification->via($owner);

        $this->assertContains('database', $channels);
        $this->assertNotContains(FcmChannel::class, $channels);
    }

    public function test_processed_notification_fcm_message_payload_is_valid(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        $category = Category::factory()->forUser($owner)->create();
        $category->workspaces()->attach($workspace->id);

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
            'amount' => '499.00',
            'description' => 'Internet',
            'frequency' => 'monthly',
            'next_due_date' => '2026-07-20',
            'is_active' => true,
            'reminders_enabled' => true,
        ]);

        $occurrence = FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'due_date' => '2026-07-20',
            'status' => 'pending',
        ]);

        $notification = new FixedExpenseProcessedNotification($fixedExpense, $occurrence);
        $fcmMessage = $notification->toFcm($owner);

        $this->assertInstanceOf(\NotificationChannels\Fcm\FcmMessage::class, $fcmMessage);

        $data = $fcmMessage->toArray();

        // The notification body must include the description and formatted amount.
        $this->assertArrayHasKey('notification', $data);
        $this->assertSame('Gasto fijo procesado', $data['notification']['title']);
        $this->assertStringContainsString('Internet', $data['notification']['body']);
        $this->assertStringContainsString('499.00', $data['notification']['body']);

        // The data payload must carry the discriminator keys FCM clients
        // consume to route/click the message.
        $this->assertSame('fixed_expense_processed', $data['data']['type']);
        $this->assertSame($fixedExpense->id, $data['data']['fixed_expense_id']);
        $this->assertSame($occurrence->id, $data['data']['occurrence_id']);
        $this->assertSame($workspace->id, $data['data']['workspace_id']);
    }

    public function test_processed_notification_is_not_resent_when_occurrence_already_exists(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $owner->assignRole('user');

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        $category = Category::factory()->forUser($owner)->create();
        $category->workspaces()->attach($workspace->id);

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
            'amount' => '50.00',
            'description' => 'Duplicado',
            'frequency' => 'monthly',
            'next_due_date' => now()->toDateString(),
            'is_active' => true,
            'reminders_enabled' => true,
        ]);

        // Occurrence already exists, so the action must NOT notify.
        FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'due_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $this->action->execute();

        Notification::assertNotSentTo($owner, FixedExpenseProcessedNotification::class);
    }
}
