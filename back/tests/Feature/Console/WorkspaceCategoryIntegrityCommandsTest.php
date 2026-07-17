<?php

namespace Tests\Feature\Console;

use App\Models\Budget;
use App\Models\BudgetAdjustment;
use App\Models\Category;
use App\Models\Expense;
use App\Models\FixedExpense;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceCategoryIntegrityCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_audit_command_reports_personal_and_shared_inconsistencies(): void
    {
        [$personalWorkspace, $personalWrongCategory] = $this->createPersonalMismatch();
        [$sharedWorkspace, $sharedWrongCategory] = $this->createSharedMismatch();

        Budget::factory()->create([
            'workspace_id' => $sharedWorkspace->id,
            'category_id' => $sharedWrongCategory->id,
        ]);

        BudgetAdjustment::query()->create([
            'workspace_id' => $sharedWorkspace->id,
            'month' => now()->startOfMonth()->toDateString(),
            'from_category_id' => $sharedWrongCategory->id,
            'to_category_id' => $personalWrongCategory->id,
            'amount' => 50,
            'reason' => 'audit check',
            'user_id' => $sharedWorkspace->owner_id,
        ]);

        $this->artisan('categories:audit-workspace-integrity')
            ->expectsOutputToContain('personal_category_owner_mismatch')
            ->expectsOutputToContain('shared_category_not_linked')
            ->expectsOutputToContain('requires_manual_review')
            ->assertSuccessful();
    }

    public function test_sanitize_command_is_dry_run_by_default(): void
    {
        [$personalWorkspace, $personalWrongCategory] = $this->createPersonalMismatch();
        [$sharedWorkspace, $sharedWrongCategory] = $this->createSharedMismatch();

        BudgetAdjustment::query()->create([
            'workspace_id' => $sharedWorkspace->id,
            'month' => now()->startOfMonth()->toDateString(),
            'from_category_id' => $sharedWrongCategory->id,
            'to_category_id' => $personalWrongCategory->id,
            'amount' => 25,
            'reason' => 'dry-run check',
            'user_id' => $sharedWorkspace->owner_id,
        ]);

        $this->artisan('categories:sanitize-workspace-integrity')
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('Requires manual review')
            ->assertSuccessful();

        $this->assertDatabaseMissing('category_workspace', [
            'workspace_id' => $sharedWorkspace->id,
            'category_id' => $sharedWrongCategory->id,
        ]);

        $this->assertDatabaseMissing('category_workspace', [
            'workspace_id' => $personalWorkspace->id,
            'category_id' => $personalWrongCategory->id,
        ]);
    }

    public function test_sanitize_command_with_apply_attaches_only_safe_shared_pairs_and_is_idempotent(): void
    {
        [$sharedWorkspace, $sharedWrongCategory] = $this->createSharedMismatch();

        $otherOwner = User::factory()->create();
        $otherOwner->assignRole('user');
        $unsafeCategory = Category::factory()->forUser($otherOwner)->create();

        FixedExpense::factory()->create([
            'workspace_id' => $sharedWorkspace->id,
            'user_id' => $sharedWorkspace->owner_id,
            'category_id' => $unsafeCategory->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
        ]);

        $this->artisan('categories:sanitize-workspace-integrity', ['--apply' => true])
            ->expectsOutputToContain('Applied safe fixes only')
            ->expectsOutputToContain('shared_category_owner_mismatch')
            ->assertSuccessful();

        $this->assertDatabaseHas('category_workspace', [
            'workspace_id' => $sharedWorkspace->id,
            'category_id' => $sharedWrongCategory->id,
        ]);

        $this->assertDatabaseMissing('category_workspace', [
            'workspace_id' => $sharedWorkspace->id,
            'category_id' => $unsafeCategory->id,
        ]);

        $this->artisan('categories:sanitize-workspace-integrity', ['--apply' => true])
            ->expectsOutputToContain('Applied safe fixes only')
            ->assertSuccessful();
    }

    /**
     * @return array{Workspace, Category}
     */
    private function createPersonalMismatch(): array
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');

        $personalWorkspace = Workspace::factory()->personal()->create([
            'owner_id' => $owner->id,
        ]);

        $otherUser = User::factory()->create();
        $otherUser->assignRole('user');

        $wrongCategory = Category::factory()->forUser($otherUser)->create();

        Expense::factory()->create([
            'workspace_id' => $personalWorkspace->id,
            'user_id' => $owner->id,
            'category_id' => $wrongCategory->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
        ]);

        return [$personalWorkspace, $wrongCategory];
    }

    /**
     * @return array{Workspace, Category}
     */
    private function createSharedMismatch(): array
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');

        $sharedWorkspace = Workspace::factory()->create([
            'owner_id' => $owner->id,
            'type' => 'familiar',
        ]);

        $sharedWorkspace->members()->attach($owner->id, ['role' => 'admin']);

        $wrongCategory = Category::factory()->forUser($owner)->create();

        FixedExpense::factory()->create([
            'workspace_id' => $sharedWorkspace->id,
            'user_id' => $owner->id,
            'category_id' => $wrongCategory->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
        ]);

        return [$sharedWorkspace, $wrongCategory];
    }
}
