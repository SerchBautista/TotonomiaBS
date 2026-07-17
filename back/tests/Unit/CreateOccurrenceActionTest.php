<?php

namespace Tests\Unit;

use App\Actions\CreateOccurrenceAction;
use App\Models\FixedExpense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class CreateOccurrenceActionTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_creates_pending_occurrence(): void
    {
        ['workspace' => $workspace, 'category' => $cat, 'card' => $card, 'user' => $user] = $this->createUserWithWorkspace();

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'next_due_date' => now()->toDateString(),
        ]);

        $action = new CreateOccurrenceAction;
        $occurrence = $action->execute($fixedExpense);

        $this->assertEquals('pending', $occurrence->status);
        $this->assertEquals($fixedExpense->amount, $occurrence->suggested_amount);
        $this->assertDatabaseCount('fixed_expense_occurrences', 1);
    }

    public function test_is_idempotent_on_double_execution(): void
    {
        ['workspace' => $workspace, 'category' => $cat, 'card' => $card, 'user' => $user] = $this->createUserWithWorkspace();

        $fixedExpense = FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'next_due_date' => now()->toDateString(),
        ]);

        $action = new CreateOccurrenceAction;
        $action->execute($fixedExpense);
        $action->execute($fixedExpense);

        $this->assertDatabaseCount('fixed_expense_occurrences', 1);
    }
}
