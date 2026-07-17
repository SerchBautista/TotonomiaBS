<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_correct_kpi_structure(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('test-token')->accessToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'kpis' => [
                        'users_total',
                        'users_registered_today',
                        'users_registered_week',
                        'email_pending_verification',
                        'premium_active_total',
                    ],
                    'recent_users',
                ],
            ]);
    }

    public function test_dashboard_returns_recent_users(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('test-token')->accessToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk();

        $recentUsers = $response->json('data.recent_users');
        $this->assertIsArray($recentUsers);
        $this->assertLessThanOrEqual(5, count($recentUsers));

        if (count($recentUsers) > 0) {
            $this->assertArrayHasKey('id', $recentUsers[0]);
            $this->assertArrayHasKey('name', $recentUsers[0]);
            $this->assertArrayHasKey('email', $recentUsers[0]);
            $this->assertArrayHasKey('registered_at', $recentUsers[0]);
            $this->assertArrayHasKey('plan', $recentUsers[0]);
        }
    }

    public function test_regular_user_cannot_access_dashboard(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'user@example.com')->firstOrFail();
        $token = $user->createToken('test-token')->accessToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertForbidden();
    }
}
