<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAdministratorCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_administrators_and_load_role_permission_options(): void
    {
        $this->seed();

        $newAdmin = User::query()->create([
            'name' => 'Second Admin',
            'email' => 'second-admin@example.com',
            'password' => 'StrongPass123',
        ]);
        $newAdmin->syncRoles(['admin']);
        $newAdmin->syncPermissions(['administrators.view']);

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('test-token')->accessToken;

        $listResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/administrators?search=second-admin');

        $listResponse
            ->assertOk()
            ->assertJsonPath('meta.search', 'second-admin')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.email', 'second-admin@example.com')
            ->assertJsonPath('data.items.0.roles.0', 'admin');

        $optionsResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/administrators/options');

        $optionsResponse
            ->assertOk()
            ->assertJsonFragment(['admin'])
            ->assertJsonFragment(['administrators.view']);
    }

    public function test_admin_can_create_update_and_delete_administrator_with_roles_and_permissions(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('test-token')->accessToken;

        $createResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/admin/administrators', [
                'name' => 'Managed Admin',
                'email' => 'managed-admin@example.com',
                'password' => 'StrongPass123',
                'password_confirmation' => 'StrongPass123',
                'roles' => ['admin'],
                'permissions' => ['administrators.view', 'administrators.create'],
            ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.item.email', 'managed-admin@example.com')
            ->assertJsonPath('data.item.roles.0', 'admin');

        $administratorId = $createResponse->json('data.item.id');

        $updateResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/admin/administrators/'.$administratorId, [
                'name' => 'Managed Admin Updated',
                'email' => 'managed-admin-updated@example.com',
                'password' => null,
                'password_confirmation' => null,
                'roles' => ['admin'],
                'permissions' => ['dashboard.view'],
            ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.item.name', 'Managed Admin Updated')
            ->assertJsonPath('data.item.email', 'managed-admin-updated@example.com')
            ->assertJsonPath('data.item.direct_permissions.0', 'dashboard.view');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/admin/administrators/'.$administratorId)
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $administratorId]);
    }

    public function test_regular_user_cannot_access_administrator_module_routes(): void
    {
        $this->seed();

        $user = User::query()->where('email', 'user@example.com')->firstOrFail();
        $token = $user->createToken('test-token')->accessToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/administrators')
            ->assertForbidden();
    }

    public function test_admin_cannot_delete_own_administrator_account(): void
    {
        $this->seed();

        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $token = $admin->createToken('test-token')->accessToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/admin/administrators/'.$admin->id)
            ->assertUnprocessable()
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'cannot_delete_self')
            ->assertJsonPath('message', 'You cannot delete your own administrator account.');
    }
}
