<?php

namespace Tests\Unit\Policies;

use App\Models\Card;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use App\Models\Workspace;
use App\Policies\ExpensePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpensePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'premium', 'guard_name' => 'api']);
    }

    public function test_member_can_view_any_expenses_in_workspace(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);
        $workspace->members()->attach($member->id, ['role' => 'member']);

        $policy = new ExpensePolicy;

        $this->assertTrue($policy->viewAny($member, $workspace));
    }

    public function test_non_member_cannot_view_any_expenses_in_workspace(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $policy = new ExpensePolicy;

        $this->assertFalse($policy->viewAny($outsider, $workspace));
    }

    public function test_workspace_member_can_view_expense(): void
    {
        [$workspace, $expense, $owner, $member] = $this->makeExpenseContext();

        $policy = new ExpensePolicy;

        $this->assertTrue($policy->view($member, $expense));
    }

    public function test_non_member_cannot_view_expense(): void
    {
        [$workspace, $expense, $owner] = $this->makeExpenseContext();
        $outsider = User::factory()->create();

        $policy = new ExpensePolicy;

        $this->assertFalse($policy->view($outsider, $expense));
    }

    public function test_workspace_owner_can_create_expense(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $policy = new ExpensePolicy;

        $this->assertTrue($policy->create($owner, $workspace));
    }

    public function test_guest_can_create_expense_in_premium_workspace(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('premium');
        $guest = User::factory()->create();

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);
        $workspace->members()->attach($guest->id, ['role' => 'guest']);

        $policy = new ExpensePolicy;

        $this->assertTrue($policy->create($guest, $workspace));
    }

    public function test_non_member_cannot_create_expense(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $outsider = User::factory()->create();

        $policy = new ExpensePolicy;

        $this->assertFalse($policy->create($outsider, $workspace));
    }

    public function test_admin_cannot_create_in_free_workspace(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);
        $workspace->members()->attach($admin->id, ['role' => 'admin']);
        // Owner is NOT premium, so non-owners are blocked.

        $policy = new ExpensePolicy;

        $this->assertFalse($policy->create($admin, $workspace));
    }

    public function test_workspace_owner_can_update_expense(): void
    {
        [$workspace, $expense, $owner] = $this->makeExpenseContext();

        $policy = new ExpensePolicy;

        $this->assertTrue($policy->update($owner, $expense));
    }

    public function test_creator_can_update_own_expense_in_premium_workspace(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('premium');
        $member = User::factory()->create();

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);
        $workspace->members()->attach($member->id, ['role' => 'member']);

        $expense = $this->makeExpense($workspace, $member);

        $policy = new ExpensePolicy;

        $this->assertTrue($policy->update($member, $expense));
    }

    public function test_guest_can_update_expense_in_premium_workspace(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('premium');
        $guest = User::factory()->create();

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);
        $workspace->members()->attach($guest->id, ['role' => 'guest']);

        $expense = $this->makeExpense($workspace, $owner);

        $policy = new ExpensePolicy;

        $this->assertTrue($policy->update($guest, $expense));
    }

    public function test_viewer_cannot_update_expense(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('premium');
        $viewer = User::factory()->create();

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);
        $workspace->members()->attach($viewer->id, ['role' => 'viewer']);

        $expense = $this->makeExpense($workspace, $owner);

        $policy = new ExpensePolicy;

        $this->assertFalse($policy->update($viewer, $expense));
    }

    public function test_non_member_cannot_update_expense(): void
    {
        [$workspace, $expense, $owner] = $this->makeExpenseContext();
        $outsider = User::factory()->create();

        $policy = new ExpensePolicy;

        $this->assertFalse($policy->update($outsider, $expense));
    }

    public function test_delete_follows_same_rules_as_update(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('premium');
        $viewer = User::factory()->create();

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);
        $workspace->members()->attach($viewer->id, ['role' => 'viewer']);

        $expense = $this->makeExpense($workspace, $owner);

        $policy = new ExpensePolicy;

        $this->assertTrue($policy->delete($owner, $expense));
        $this->assertFalse($policy->delete($viewer, $expense));
    }

    public function test_workspace_owner_can_delete_expense(): void
    {
        [$workspace, $expense, $owner] = $this->makeExpenseContext();

        $policy = new ExpensePolicy;

        $this->assertTrue($policy->delete($owner, $expense));
    }

    public function test_non_member_cannot_delete_expense(): void
    {
        [$workspace, $expense, $owner] = $this->makeExpenseContext();
        $outsider = User::factory()->create();

        $policy = new ExpensePolicy;

        $this->assertFalse($policy->delete($outsider, $expense));
    }

    public function test_viewer_can_view_expense_for_read_only_access(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('premium');
        $viewer = User::factory()->create();

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);
        $workspace->members()->attach($viewer->id, ['role' => 'viewer']);

        $expense = $this->makeExpense($workspace, $owner);

        $policy = new ExpensePolicy;

        $this->assertTrue($policy->view($viewer, $expense));
        $this->assertFalse($policy->update($viewer, $expense));
        $this->assertFalse($policy->delete($viewer, $expense));
    }

    /**
     * @return array{0: Workspace, 1: Expense, 2: User, 3: User}
     */
    private function makeExpenseContext(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);
        $workspace->members()->attach($member->id, ['role' => 'member']);

        $expense = $this->makeExpense($workspace, $owner);

        return [$workspace, $expense, $owner, $member];
    }

    private function makeExpense(Workspace $workspace, User $user): Expense
    {
        $category = Category::factory()->forUser($user)->create();
        $card = Card::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

        return Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);
    }
}
