<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class AnalyticsMemberSplitTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_member_can_get_member_split_for_current_month(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $today = Carbon::now()->toDateString();

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '120.00',
            'date' => $today,
        ]);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/analytics/member-split");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'month',
                    'total',
                    'member_count',
                    'fair_share',
                    'members' => [['id', 'name', 'paid', 'balance']],
                    'settlements',
                ],
            ])
            ->assertJsonPath('data.member_count', 1)
            ->assertJsonPath('data.total', '120.00');
    }

    public function test_member_split_respects_year_and_month_filters(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        // Expense in October 2025
        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '300.00',
            'date' => '2025-10-15',
        ]);

        // Expense in November 2025
        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '600.00',
            'date' => '2025-11-15',
        ]);

        $response = $this->getJson(
            "/api/v1/workspaces/{$workspace->id}/analytics/member-split?year=2025&month=10"
        );

        $response->assertOk()
            ->assertJsonPath('data.month', '2025-10')
            ->assertJsonPath('data.total', '300.00');

        $response = $this->getJson(
            "/api/v1/workspaces/{$workspace->id}/analytics/member-split?year=2025&month=11"
        );

        $response->assertOk()
            ->assertJsonPath('data.month', '2025-11')
            ->assertJsonPath('data.total', '600.00');
    }

    public function test_non_member_cannot_get_member_split(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();

        $outsider = User::factory()->create();
        $outsider->assignRole('user');
        $this->actingAsUser($outsider);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/analytics/member-split")
            ->assertForbidden();
    }

    public function test_member_split_for_missing_workspace_returns_404(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->getJson('/api/v1/workspaces/00000000-0000-0000-0000-000000000000/analytics/member-split')
            ->assertNotFound();
    }
}
