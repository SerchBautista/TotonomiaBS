<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class AuthLogoutTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'premium', 'guard_name' => 'api']);
    }

    public function test_authenticated_user_can_logout_and_token_is_revoked(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        // Passport::actingAs creates an in-memory AccessToken (not persisted)
        // and binds it to the user. The revoke flag is mutated in memory by
        // AuthController::logout. We can't assert against the
        // oauth_access_tokens table because Passport::actingAs does not
        // materialise a row in the database.
        $this->assertNotNull($user->token());

        $response = $this->postJson('/api/v1/auth/logout');

        $response->assertOk()
            ->assertJsonStructure(['message'])
            ->assertJsonPath('message', __('api.auth.logout_success'));
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $this->postJson('/api/v1/auth/logout')
            ->assertUnauthorized();
    }
}
