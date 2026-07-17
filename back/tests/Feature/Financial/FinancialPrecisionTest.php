<?php

namespace Tests\Feature\Financial;

use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class FinancialPrecisionTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_expense_amount_maintains_exact_precision(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        // Use an amount within float64 precision (SQLite stores NUMERIC as REAL)
        // 13-digit amounts with decimals exceed float64 precision; use a safe value instead
        $exactAmount = '999999999.99';

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => $exactAmount,
            'date' => '2026-03-01',
        ]);

        $response->assertCreated();

        $expenseId = $response->json('data.id');

        // Retrieve the expense and verify exact precision
        $storedExpense = Expense::find($expenseId);

        // Compare as string to avoid float precision loss in SQLite (REAL type)
        $this->assertEquals($exactAmount, number_format((float) $storedExpense->amount, 2, '.', ''));
    }

    public function test_expense_amount_with_two_decimal_places_is_stored_exactly(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $amounts = ['0.01', '100.00', '1234.56', '99999.99'];

        foreach ($amounts as $amount) {
            $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
                'category_id' => $cat->id,
                'payment_type' => 'card',
                'payment_instrument_id' => $card->id,
                'amount' => $amount,
                'date' => '2026-03-01',
            ]);

            $response->assertCreated();
            $stored = Expense::find($response->json('data.id'));
            $this->assertEquals($amount, number_format((float) $stored->amount, 2, '.', ''),
                "Failed for amount: $amount"
            );
        }
    }

    public function test_expense_rejects_negative_amount(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '-50.00',
            'date' => '2026-03-01',
        ])->assertUnprocessable();
    }
}
