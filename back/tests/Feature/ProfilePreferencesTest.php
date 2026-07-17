<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class ProfilePreferencesTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $userRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
        $permissions = ['profile.view', 'profile.update'];
        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api',
            ]);
        }
        $userRole->syncPermissions($permissions);
    }

    public function test_user_can_update_profile_preferences(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $response = $this->putJson('/api/v1/user/profile', [
            'theme' => 'light',
            'locale' => 'en',
            'timezone' => 'America/Mexico_City',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.theme', 'light')
            ->assertJsonPath('data.user.locale', 'en')
            ->assertJsonPath('data.user.timezone', 'America/Mexico_City');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'theme' => 'light',
            'locale' => 'en',
            'timezone' => 'America/Mexico_City',
        ]);
    }

    public function test_invalid_theme_returns_validation_error(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $response = $this->putJson('/api/v1/user/profile', [
            'theme' => 'invalid',
            'locale' => 'es',
            'timezone' => 'UTC',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['theme'], 'fieldErrors');
    }

    public function test_invalid_timezone_returns_validation_error(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $response = $this->putJson('/api/v1/user/profile', [
            'theme' => 'dark',
            'locale' => 'es',
            'timezone' => 'NotAReal/Timezone',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['timezone'], 'fieldErrors');
    }

    public function test_unauthenticated_user_cannot_update_preferences(): void
    {
        $this->putJson('/api/v1/user/profile', [
            'theme' => 'dark',
            'locale' => 'es',
            'timezone' => 'UTC',
        ])->assertUnauthorized();
    }

    public function test_profile_show_includes_preferences(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $user->update([
            'theme' => 'light',
            'locale' => 'en',
            'timezone' => 'America/Bogota',
        ]);
        $this->actingAsUser($user);

        $response = $this->getJson('/api/v1/user/profile');

        $response->assertOk()
            ->assertJsonPath('data.user.theme', 'light')
            ->assertJsonPath('data.user.locale', 'en')
            ->assertJsonPath('data.user.timezone', 'America/Bogota');
    }
}
