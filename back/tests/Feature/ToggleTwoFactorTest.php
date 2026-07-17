<?php

namespace Tests\Feature;

use App\Models\TwoFactorSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ToggleTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function authenticatedUser(): User
    {
        $this->seed();

        return User::where('email', 'user@example.com')->first();
    }

    public function test_user_can_enable_2fa_with_correct_password(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->actingAs($user, 'api')
            ->putJson('/api/v1/user/two-factor', [
                'enabled' => true,
                'password' => 'StrongPass123',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.two_factor_enabled', true);

        $user->refresh();
        $this->assertTrue($user->two_factor_enabled);
    }

    public function test_user_can_disable_2fa_with_correct_password(): void
    {
        $user = $this->authenticatedUser();
        $user->update(['two_factor_enabled' => true]);

        $response = $this->actingAs($user, 'api')
            ->putJson('/api/v1/user/two-factor', [
                'enabled' => false,
                'password' => 'StrongPass123',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.two_factor_enabled', false);

        $user->refresh();
        $this->assertFalse($user->two_factor_enabled);
    }

    public function test_toggle_2fa_with_wrong_password_returns_422(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->actingAs($user, 'api')
            ->putJson('/api/v1/user/two-factor', [
                'enabled' => true,
                'password' => 'WrongPassword123',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('code', 'invalid_password')
            ->assertJsonPath('fieldErrors.password.0', 'The current password is incorrect.');
    }

    public function test_toggle_2fa_without_permission_returns_403(): void
    {
        $user = $this->authenticatedUser();

        // Remove the two-factor.update permission from the user role
        $user->revokePermissionTo('two-factor.update');

        // Also remove it from the role to ensure it's fully revoked
        $user->removeRole('user');
        $user->assignRole('user');

        // Clear permission cache and re-sync without the permission
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $role = \Spatie\Permission\Models\Role::where('name', 'user')->where('guard_name', 'api')->first();
        $role->revokePermissionTo('two-factor.update');

        $response = $this->actingAs($user, 'api')
            ->putJson('/api/v1/user/two-factor', [
                'enabled' => true,
                'password' => 'StrongPass123',
            ]);

        $response->assertStatus(403);
    }

    public function test_disable_2fa_removes_active_sessions(): void
    {
        $user = $this->authenticatedUser();
        $user->update(['two_factor_enabled' => true]);

        // Create some active sessions
        TwoFactorSession::create([
            'user_id' => $user->id,
            'token' => (string) Str::uuid(),
            'code_hash' => Hash::make('123456'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);

        TwoFactorSession::create([
            'user_id' => $user->id,
            'token' => (string) Str::uuid(),
            'code_hash' => Hash::make('654321'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
        ]);

        $this->assertDatabaseCount('two_factor_sessions', 2);

        $response = $this->actingAs($user, 'api')
            ->putJson('/api/v1/user/two-factor', [
                'enabled' => false,
                'password' => 'StrongPass123',
            ]);

        $response->assertOk();
        $this->assertDatabaseCount('two_factor_sessions', 0);
    }

    public function test_toggle_2fa_requires_authentication(): void
    {
        $response = $this->putJson('/api/v1/user/two-factor', [
            'enabled' => true,
            'password' => 'StrongPass123',
        ]);

        $response->assertStatus(401);
    }

    public function test_toggle_2fa_validates_required_fields(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->actingAs($user, 'api')
            ->putJson('/api/v1/user/two-factor', []);

        $response
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error');
    }

    public function test_toggle_2fa_validates_enabled_is_boolean(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->actingAs($user, 'api')
            ->putJson('/api/v1/user/two-factor', [
                'enabled' => 'not-a-boolean',
                'password' => 'StrongPass123',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error');
    }

    public function test_user_resource_exposes_two_factor_enabled(): void
    {
        $user = $this->authenticatedUser();
        $user->update(['two_factor_enabled' => true]);

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/v1/auth/me');

        $response
            ->assertOk()
            ->assertJsonPath('user.two_factor_enabled', true);
    }

    public function test_user_resource_exposes_two_factor_disabled(): void
    {
        $user = $this->authenticatedUser();

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/v1/auth/me');

        $response
            ->assertOk()
            ->assertJsonPath('user.two_factor_enabled', false);
    }
}
