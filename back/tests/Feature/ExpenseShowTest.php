<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class ExpenseShowTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_workspace_member_can_view_own_expense(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '99.50',
            'description' => 'Compra propia',
        ]);

        $this->actingAsUser($user);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $expense->id)
            ->assertJsonPath('data.amount', '99.50')
            ->assertJsonPath('data.description', 'Compra propia')
            ->assertJsonPath('data.workspace_id', $workspace->id)
            ->assertJsonPath('data.payment_type', 'card');
    }

    public function test_workspace_member_can_view_another_members_expense(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();
        $member = User::factory()->create();
        $workspace->members()->attach($member->id, ['role' => 'admin']);

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $this->actingAsUser($member);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $expense->id);
    }

    public function test_workspace_owner_can_view_any_expense(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();
        $otherMember = User::factory()->create();
        $workspace->members()->attach($otherMember->id, ['role' => 'guest']);

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $otherMember->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $this->actingAsUser($owner);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $expense->id);
    }

    public function test_viewer_can_view_expense_for_read(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();
        $viewer = User::factory()->create();
        $workspace->members()->attach($viewer->id, ['role' => 'viewer']);

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $this->actingAsUser($viewer);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $expense->id);
    }

    public function test_non_member_cannot_view_expense(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $outsider = User::factory()->create();
        $this->actingAsUser($outsider);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}")
            ->assertForbidden();
    }

    public function test_nonexistent_expense_returns_404(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/expenses/00000000-0000-0000-0000-000000000000")
            ->assertNotFound();
    }

    public function test_nonexistent_workspace_returns_404(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->getJson('/api/v1/workspaces/00000000-0000-0000-0000-000000000000/expenses/00000000-0000-0000-0000-000000000000')
            ->assertNotFound();
    }

    public function test_show_response_contains_expected_resource_fields(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'fixed_expense_id' => null,
            'paid_by_user_id' => $user->id,
            'amount' => '42.00',
            'description' => 'Verificar shape',
            'date' => '2026-04-15',
        ]);

        $this->actingAsUser($user);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'workspace_id',
                    'amount',
                    'date',
                    'description',
                    'category' => ['id', 'name'],
                    'payment_type',
                    'payment_instrument' => ['id'],
                    'user' => ['id'],
                    'paid_by_user_id',
                    'paid_by' => ['id'],
                    'fixed_expense_id',
                    'created_at',
                    'updated_at',
                ],
            ]);

        $payload = $response->json('data');
        $this->assertSame($expense->id, $payload['id']);
        $this->assertSame($workspace->id, $payload['workspace_id']);
        $this->assertSame('42.00', $payload['amount']);
        $this->assertSame('2026-04-15', $payload['date']);
        $this->assertSame('card', $payload['payment_type']);
        $this->assertSame($user->id, $payload['paid_by_user_id']);
        $this->assertNull($payload['fixed_expense_id']);
    }

    public function test_show_includes_payment_instrument_with_card_shape(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $this->actingAsUser($user);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}")
            ->assertOk()
            ->assertJsonPath('data.payment_instrument.id', $card->id);
    }

    public function test_show_without_payment_instrument_returns_null_instrument(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat] = $this->createUserWithWorkspace();

        $expense = Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
        ]);

        $this->actingAsUser($user);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expense->id}")
            ->assertOk()
            ->assertJsonPath('data.payment_type', 'cash')
            ->assertJsonPath('data.payment_instrument', null);
    }
}
