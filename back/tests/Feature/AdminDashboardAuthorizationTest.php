<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;
use Tests\CreatesAdmin;
use Tests\TestCase;

class AdminDashboardAuthorizationTest extends TestCase
{
    use CreatesAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    public function test_unauthenticated_user_cannot_access_admin_dashboard(): void
    {
        $this->getJson('/api/v1/admin/dashboard')
            ->assertUnauthorized()
            ->assertJsonStructure(['status', 'code', 'message']);
    }

    public function test_regular_user_without_admin_role_cannot_access_admin_dashboard(): void
    {
        $regular = User::factory()->create();
        $regular->assignRole('user');

        Passport::actingAs($regular, ['*'], 'api');

        $this->getJson('/api/v1/admin/dashboard')
            ->assertForbidden()
            ->assertJsonStructure(['status', 'code', 'message']);
    }

    public function test_admin_without_dashboard_view_permission_is_forbidden(): void
    {
        // Admin role exists but the `dashboard.view` permission is stripped
        // from the role so the `api.permission:dashboard.view` middleware
        // must reject the request.
        Role::query()->where('name', 'admin')->where('guard_name', 'api')->first()
            ->revokePermissionTo('dashboard.view');

        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        $this->getJson('/api/v1/admin/dashboard')
            ->assertForbidden()
            ->assertJsonStructure(['status', 'code', 'message']);
    }

    public function test_admin_with_dashboard_view_permission_can_access_dashboard(): void
    {
        $admin = $this->createAdminUser();
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
                    'recent_users',
                ],
            ]);

        $this->assertSame(__('api.dashboard.loaded'), $response->json('message'));
        $this->assertIsArray($response->json('data.recent_users'));
    }
}
