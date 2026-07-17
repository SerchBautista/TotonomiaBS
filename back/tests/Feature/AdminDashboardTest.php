<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\CreatesAdmin;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use CreatesAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    public function test_dashboard_recent_users_use_admin_user_list_resource_shape(): void
    {
        $admin = $this->createAdminUser();
        $regular = User::factory()->create([
            'name' => 'Recent User',
            'email' => 'recent-user@example.com',
        ]);
        $regular->assignRole('user');

        $this->actingAsAdmin($admin);

        $response = $this->getJson('/api/v1/admin/dashboard')
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
                    'recent_users' => [
                        '*' => [
                            'id',
                            'name',
                            'email',
                            'plan',
                            'registered_at',
                        ],
                    ],
                ],
            ]);

        $recentUsers = $response->json('data.recent_users');
        $this->assertIsArray($recentUsers);
        $this->assertNotEmpty($recentUsers);

        $first = collect($recentUsers)->firstWhere('email', 'recent-user@example.com');
        $this->assertNotNull($first);
        $this->assertArrayHasKey('registered_at', $first);
        $this->assertArrayNotHasKey('created_at', $first);
    }

    public function test_dashboard_requires_admin_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        Passport::actingAs($user, ['*'], 'api');

        $this->getJson('/api/v1/admin/dashboard')
            ->assertForbidden();
    }
}
