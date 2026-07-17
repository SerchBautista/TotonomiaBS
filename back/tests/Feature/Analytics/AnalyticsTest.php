<?php

namespace Tests\Feature\Analytics;

use App\Models\Expense;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_authenticated_user_can_get_summary_for_their_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $today = Carbon::now()->toDateString();

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
            'date' => $today,
        ]);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '50.00',
            'date' => $today,
        ]);

        $this->actingAsUser($user);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/analytics/summary");

        $response->assertOk()
            ->assertJsonPath('data.total', '150.00')
            ->assertJsonStructure([
                'data' => [
                    'total',
                    'period' => ['from', 'to'],
                    'by_category',
                    'by_payment_method',
                ],
            ]);
    }

    public function test_summary_filters_by_date_range(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $inRange = Carbon::now()->startOfMonth()->toDateString();
        $outRange = Carbon::now()->subMonth()->startOfMonth()->toDateString();

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '200.00',
            'date' => $inRange,
        ]);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '999.00',
            'date' => $outRange,
        ]);

        $this->actingAsUser($user);

        $from = Carbon::now()->startOfMonth()->toDateString();
        $to = Carbon::now()->toDateString();

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/analytics/summary?from={$from}&to={$to}");

        $response->assertOk()
            ->assertJsonPath('data.total', '200.00');
    }

    public function test_heatmap_returns_correct_day_totals(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        $year = (int) Carbon::now()->year;
        $month = (int) Carbon::now()->month;
        $day = Carbon::createFromDate($year, $month, 1)->toDateString();

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '75.00',
            'date' => $day,
        ]);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '25.00',
            'date' => $day,
        ]);

        $this->actingAsUser($user);

        $response = $this->getJson(
            "/api/v1/workspaces/{$workspace->id}/analytics/heatmap?year={$year}&month={$month}"
        );

        $response->assertOk();

        $entries = $response->json('data');
        $this->assertNotEmpty($entries);

        $entry = collect($entries)->firstWhere('date', $day);
        $this->assertNotNull($entry);
        $this->assertEquals('100.00', $entry['total']);
        $this->assertEquals(2, $entry['count']);
    }

    public function test_projection_returns_correct_structure(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '300.00',
            'date' => Carbon::now()->toDateString(),
        ]);

        $this->actingAsUser($user);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/analytics/projection");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'current_month_total',
                    'days_elapsed',
                    'days_in_month',
                    'daily_average',
                    'projected_total',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals('300.00', $data['current_month_total']);
        $this->assertIsInt($data['days_elapsed']);
        $this->assertIsInt($data['days_in_month']);
    }

    public function test_unauthorized_user_cannot_access_analytics(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();
        $outsider = User::factory()->create();
        $this->actingAsUser($outsider);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/analytics/summary")
            ->assertForbidden();

        $this->getJson("/api/v1/workspaces/{$workspace->id}/analytics/heatmap")
            ->assertForbidden();

        $this->getJson("/api/v1/workspaces/{$workspace->id}/analytics/projection")
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_analytics(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();

        $this->getJson("/api/v1/workspaces/{$workspace->id}/analytics/summary")
            ->assertUnauthorized();
    }
}
