<?php

namespace Tests\Feature\Automation;

use App\Actions\ProcessFixedExpensesAction;
use App\Models\FixedExpense;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class FixedExpensesCronTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_cron_creates_occurrence_and_updates_next_due_date(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $today = Carbon::today();

        $fixed = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '1200.00',
            'frequency' => 'monthly',
            'next_due_date' => $today->toDateString(),
            'is_active' => true,
        ]);

        $action = app(ProcessFixedExpensesAction::class);
        $result = $action->execute($today);

        $this->assertEquals(1, $result['processed']);
        $this->assertEquals(0, $result['failed']);

        // Occurrence was created (no direct expense)
        $this->assertDatabaseHas('fixed_expense_occurrences', [
            'fixed_expense_id' => $fixed->id,
            'suggested_amount' => '1200.00',
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('expenses', 0);

        // next_due_date was advanced by 1 month
        $fixed->refresh();
        $this->assertEquals($today->addMonth()->toDateString(), $fixed->next_due_date->toDateString());
    }

    public function test_cron_skips_inactive_fixed_expenses(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'frequency' => 'monthly',
            'next_due_date' => now()->toDateString(),
            'is_active' => false,
        ]);

        $action = app(ProcessFixedExpensesAction::class);
        $result = $action->execute();

        $this->assertEquals(0, $result['processed']);
        $this->assertDatabaseCount('fixed_expense_occurrences', 0);
    }

    public function test_cron_is_idempotent_via_unique_constraint(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'frequency' => 'monthly',
            'next_due_date' => now()->subDay()->toDateString(),
            'is_active' => true,
        ]);

        $action = app(ProcessFixedExpensesAction::class);

        // Run once — creates occurrence, advances next_due_date to future
        $action->execute();
        $this->assertDatabaseCount('fixed_expense_occurrences', 1);

        // Run again — next_due_date is now in future, nothing to process
        $action->execute();
        $this->assertDatabaseCount('fixed_expense_occurrences', 1);
    }

    public function test_artisan_command_processes_fixed_expenses(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'frequency' => 'monthly',
            'next_due_date' => now()->toDateString(),
            'is_active' => true,
        ]);

        $this->artisan('expenses:process-fixed')
            ->assertSuccessful();

        $this->assertDatabaseCount('fixed_expense_occurrences', 1);
        $this->assertDatabaseCount('expenses', 0);
    }
}
