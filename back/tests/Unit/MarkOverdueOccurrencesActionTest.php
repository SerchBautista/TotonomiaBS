<?php

namespace Tests\Unit;

use App\Actions\MarkOverdueOccurrencesAction;
use App\Models\FixedExpense;
use App\Models\FixedExpenseOccurrence;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class MarkOverdueOccurrencesActionTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_marks_pending_past_due_occurrences_as_overdue(): void
    {
        ['workspace' => $workspace, 'category' => $cat, 'card' => $card, 'user' => $user] = $this->createUserWithWorkspace();

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $past = FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'status' => 'pending',
            'due_date' => now()->subDays(3)->toDateString(),
        ]);

        $future = FixedExpenseOccurrence::factory()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'status' => 'pending',
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $action = new MarkOverdueOccurrencesAction;
        $count = $action->execute(Carbon::today());

        $this->assertEquals(1, $count);
        $this->assertEquals('overdue', $past->fresh()->status);
        $this->assertEquals('pending', $future->fresh()->status);
    }

    public function test_does_not_affect_already_paid_occurrences(): void
    {
        ['workspace' => $workspace, 'category' => $cat, 'card' => $card, 'user' => $user] = $this->createUserWithWorkspace();

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $paid = FixedExpenseOccurrence::factory()->paid()->create([
            'fixed_expense_id' => $fixedExpense->id,
            'due_date' => now()->subDays(5)->toDateString(),
        ]);

        $action = new MarkOverdueOccurrencesAction;
        $count = $action->execute(Carbon::today());

        $this->assertEquals(0, $count);
        $this->assertEquals('paid', $paid->fresh()->status);
    }
}
