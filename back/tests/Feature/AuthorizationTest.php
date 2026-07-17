<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_dashboard(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('test-token')->accessToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/dashboard')
            ->assertOk();
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

    public function test_regular_user_can_access_user_profile_route(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'user@example.com')->firstOrFail();
        $token = $user->createToken('test-token')->accessToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user/profile')
            ->assertOk();
    }

    public function test_admin_can_access_user_profile_route(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('test-token')->accessToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user/profile')
            ->assertOk();
    }

    public function test_unauthenticated_user_cannot_access_user_profile_route(): void
    {
        $this->getJson('/api/v1/user/profile')
            ->assertUnauthorized();
    }

    public function test_user_without_profile_permission_cannot_access_user_profile_route(): void
    {
        $this->seed();

        $userRole = Role::findByName('user', 'api');
        $userRole->revokePermissionTo('profile.view');

        $user = User::query()->where('email', 'user@example.com')->firstOrFail();
        $token = $user->createToken('test-token')->accessToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/user/profile')
            ->assertForbidden()
            ->assertJsonPath('status', 403)
            ->assertJsonPath('code', 'forbidden')
            ->assertJsonPath('message', 'You do not have permission to access this resource.');
    }
}
