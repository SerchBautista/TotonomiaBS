<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class GenerateMonthlyExpensesCommandTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_generates_expenses_for_current_month(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $user->update(['default_workspace_id' => $workspace->id]);

        $monthStart = Carbon::now()->startOfMonth()->toDateString();
        $monthEnd = Carbon::now()->endOfMonth()->toDateString();

        $this->artisan('expenses:generate-monthly', [
            'user_id' => $user->id,
        ])->assertSuccessful();

        $this->assertSame(
            50,
            Expense::query()
                ->where('workspace_id', $workspace->id)
                ->where('user_id', $user->id)
                ->whereDate('date', '>=', $monthStart)
                ->whereDate('date', '<=', $monthEnd)
                ->count()
        );
    }

    public function test_fails_when_user_not_found(): void
    {
        $missingId = '00000000-0000-4000-8000-000000000001';

        $this->artisan('expenses:generate-monthly', [
            'user_id' => $missingId,
        ])
            ->expectsOutputToContain("User with ID [{$missingId}] not found.")
            ->assertFailed();
    }

    public function test_respects_category_budget_remaining(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();
        $user->update(['default_workspace_id' => $workspace->id]);

        Budget::factory()->create([
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'amount' => 1000.00,
            'effective_from' => Carbon::now()->startOfMonth()->toDateString(),
        ]);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => 800.00,
            'date' => Carbon::now()->startOfMonth()->addDays(2)->toDateString(),
        ]);

        $existingIds = Expense::query()->pluck('id')->all();

        $this->artisan('expenses:generate-monthly', [
            'user_id' => $user->id,
            '--count' => 5,
        ])->assertSuccessful();

        $newExpenses = Expense::query()
            ->whereNotIn('id', $existingIds)
            ->where('category_id', $category->id)
            ->get();

        $this->assertGreaterThan(0, $newExpenses->count());

        $monthStart = Carbon::now()->startOfMonth()->toDateString();
        $monthEnd = Carbon::now()->endOfMonth()->toDateString();

        $monthTotal = (float) Expense::query()
            ->where('workspace_id', $workspace->id)
            ->where('category_id', $category->id)
            ->whereDate('date', '>=', $monthStart)
            ->whereDate('date', '<=', $monthEnd)
            ->sum('amount');

        $this->assertLessThanOrEqual(1000.00, $monthTotal);
        $this->assertLessThanOrEqual(200.00, (float) $newExpenses->sum('amount'));
    }

    public function test_fails_without_categories(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();
        $user->update(['default_workspace_id' => $workspace->id]);
        $category->workspaces()->detach($workspace->id);

        $this->artisan('expenses:generate-monthly', [
            'user_id' => $user->id,
        ])
            ->expectsOutputToContain("No enabled categories found for workspace [{$workspace->id}].")
            ->assertFailed();

        $this->assertSame(0, Expense::query()->where('workspace_id', $workspace->id)->count());
    }
}
