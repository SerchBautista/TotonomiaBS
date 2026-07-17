<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_can_login_and_receive_token(): void
    {
        $this->seed();

        $response = $this->postJson('/api/v1/auth/user/login', [
            'email' => 'user@example.com',
            'password' => 'StrongPass123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.role', 'user')
            ->assertJsonStructure(['token', 'token_type', 'user']);
    }

    public function test_login_returns_message_in_spanish_when_lang_parameter_is_set(): void
    {
        $this->seed();

        $response = $this->postJson('/api/v1/auth/user/login?lang=es', [
            'email' => 'user@example.com',
            'password' => 'StrongPass123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Inicio de sesión exitoso.');
    }

    public function test_login_returns_message_in_spanish_when_lang_contains_region(): void
    {
        $this->seed();

        $response = $this->postJson('/api/v1/auth/user/login?lang=es-MX', [
            'email' => 'user@example.com',
            'password' => 'StrongPass123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Inicio de sesión exitoso.');
    }

    public function test_login_validation_errors_are_returned_in_spanish_when_lang_is_es(): void
    {
        $response = $this->postJson('/api/v1/auth/user/login?lang=es', []);

        $response
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('message', 'Los datos proporcionados no son válidos.')
            ->assertJsonPath('fieldErrors.email.0', 'El campo correo electronico es obligatorio.')
            ->assertJsonPath('fieldErrors.password.0', 'El campo contraseña es obligatorio.');
    }

    public function test_admin_can_login_using_admin_login_endpoint(): void
    {
        $this->seed();

        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'StrongPass123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonStructure(['token', 'token_type', 'user']);
    }

    public function test_admin_can_login_through_user_login_endpoint(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@example.com')->first();
        $this->assertTrue($admin->hasRole('admin'));

        $response = $this->postJson('/api/v1/auth/user/login', [
            'email' => 'admin@example.com',
            'password' => 'StrongPass123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonStructure(['token', 'token_type', 'user']);
    }

    public function test_admin_authenticated_through_user_login_endpoint_can_access_user_profile_route(): void
    {
        $this->seed();

        $login = $this->postJson('/api/v1/auth/user/login', [
            'email' => 'admin@example.com',
            'password' => 'StrongPass123',
        ]);

        $login->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$login->json('token'))
            ->getJson('/api/v1/user/profile')
            ->assertOk();
    }

    public function test_regular_user_authenticated_through_user_login_endpoint_can_access_user_profile_route(): void
    {
        $this->seed();

        $login = $this->postJson('/api/v1/auth/user/login', [
            'email' => 'user@example.com',
            'password' => 'StrongPass123',
        ]);

        $login->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$login->json('token'))
            ->getJson('/api/v1/user/profile')
            ->assertOk();
    }

    public function test_user_with_wrong_credentials_receives_invalid_credentials_on_user_login(): void
    {
        $this->seed();

        $response = $this->postJson('/api/v1/auth/user/login', [
            'email' => 'admin@example.com',
            'password' => 'WrongPass123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'invalid_credentials')
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_user_without_any_role_cannot_login_through_user_login_endpoint(): void
    {
        $this->seed();

        $user = User::factory()->create([
            'password' => Hash::make('StrongPass123'),
        ]);
        $user->syncRoles([]);
        $this->assertFalse($user->hasRole('user'));
        $this->assertFalse($user->hasRole('admin'));

        $response = $this->postJson('/api/v1/auth/user/login', [
            'email' => $user->email,
            'password' => 'StrongPass123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'invalid_credentials')
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_user_without_any_role_cannot_login_through_admin_login_endpoint(): void
    {
        $this->seed();

        $user = User::factory()->create([
            'password' => Hash::make('StrongPass123'),
        ]);
        $user->syncRoles([]);

        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => $user->email,
            'password' => 'StrongPass123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'invalid_credentials')
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_regular_user_cannot_login_through_admin_login_endpoint(): void
    {
        $this->seed();

        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'user@example.com',
            'password' => 'StrongPass123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'invalid_credentials')
            ->assertJsonPath('message', 'Invalid credentials.');
    }

    public function test_admin_login_endpoint_returns_admin_role_for_user_with_both_roles(): void
    {
        $this->seed();

        $user = \App\Models\User::query()->updateOrCreate(
            ['email' => 'dual-role@example.com'],
            [
                'name' => 'Dual Role User',
                'password' => \Illuminate\Support\Facades\Hash::make('StrongPass123'),
                'email_verified_at' => now(),
            ]
        );
        $user->assignRole(['user', 'admin']);

        $response = $this->postJson('/api/v1/auth/admin/login', [
            'email' => 'dual-role@example.com',
            'password' => 'StrongPass123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonStructure(['token', 'token_type', 'user']);
    }
}
