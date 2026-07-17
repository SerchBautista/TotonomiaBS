<?php

namespace Tests\Feature;

use App\Actions\CreateOccurrenceAction;
use App\Actions\ProcessFixedExpensesAction;
use App\Models\Category;
use App\Models\FixedExpense;
use App\Models\FixedExpenseOccurrence;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\FixedExpenseEmailNotification;
use App\Notifications\FixedExpenseProcessedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FixedExpenseEmailNotificationTest extends TestCase
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

    public function test_owner_receives_email_when_occurrence_created(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
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
            'amount' => '150.00',
            'description' => 'Netflix subscription',
            'frequency' => 'monthly',
            'next_due_date' => now()->toDateString(),
            'is_active' => true,
            'reminders_enabled' => true,
        ]);

        $this->action->execute();

        Notification::assertSentTo($owner, FixedExpenseEmailNotification::class);
    }

    public function test_workspace_members_receive_email_when_occurrence_created(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $member1 = User::factory()->create();
        $member2 = User::factory()->create();

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);
        $workspace->members()->attach($member1->id, ['role' => 'admin']);
        $workspace->members()->attach($member2->id, ['role' => 'member']);

        $category = Category::factory()->forUser($owner)->create();
        $category->workspaces()->attach($workspace->id);

        FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
            'amount' => '200.00',
            'description' => 'Office rent',
            'frequency' => 'monthly',
            'next_due_date' => now()->toDateString(),
            'is_active' => true,
            'reminders_enabled' => true,
        ]);

        $this->action->execute();

        // Verificar correo
        Notification::assertSentTo($owner, FixedExpenseEmailNotification::class);
        Notification::assertSentTo($member1, FixedExpenseEmailNotification::class);
        Notification::assertSentTo($member2, FixedExpenseEmailNotification::class);

        // Verificar push (BD + FCM)
        Notification::assertSentTo($owner, FixedExpenseProcessedNotification::class);
        Notification::assertSentTo($member1, FixedExpenseProcessedNotification::class);
        Notification::assertSentTo($member2, FixedExpenseProcessedNotification::class);
    }

    public function test_no_duplicate_email_to_owner_when_owner_is_also_member(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        $category = Category::factory()->forUser($owner)->create();
        $category->workspaces()->attach($workspace->id);

        FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
            'amount' => '100.00',
            'description' => 'Internet bill',
            'frequency' => 'monthly',
            'next_due_date' => now()->toDateString(),
            'is_active' => true,
            'reminders_enabled' => true,
        ]);

        $this->action->execute();

        Notification::assertSentToTimes($owner, FixedExpenseEmailNotification::class, 1);
    }

    public function test_no_email_sent_when_occurrence_already_exists(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
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
            'amount' => '100.00',
            'description' => 'Gym membership',
            'frequency' => 'monthly',
            'next_due_date' => now()->toDateString(),
            'is_active' => true,
            'reminders_enabled' => true,
        ]);

        FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'due_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $this->action->execute();

        Notification::assertNotSentTo($owner, FixedExpenseEmailNotification::class);
    }

    public function test_next_due_date_not_advanced_when_occurrence_already_exists(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        $category = Category::factory()->forUser($owner)->create();
        $category->workspaces()->attach($workspace->id);

        $originalDueDate = '2026-06-01';
        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
            'amount' => '100.00',
            'description' => 'Test expense',
            'frequency' => 'monthly',
            'next_due_date' => $originalDueDate,
            'is_active' => true,
            'reminders_enabled' => true,
        ]);

        // Crear ocurrencia existente
        FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'due_date' => $originalDueDate,
            'status' => 'pending',
        ]);

        $this->action->execute();

        // Recargar y verificar que next_due_date NO cambió
        $fixedExpense->refresh();
        $this->assertEquals($originalDueDate, $fixedExpense->next_due_date->toDateString());
    }
}
