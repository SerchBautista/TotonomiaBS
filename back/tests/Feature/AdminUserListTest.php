<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesAdmin;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class AdminUserListTest extends TestCase
{
    use CreatesAdmin;
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_admin_user_list_returns_items_array_without_nested_data(): void
    {
        ['user' => $regular] = $this->createUserWithWorkspace();

        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        $response = $this->getJson('/api/v1/admin/users')
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'items' => [
                        '*' => [
                            'id',
                            'name',
                            'email',
                            'plan',
                            'registered_at',
                        ],
                    ],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $items = $response->json('data.items');
        $this->assertIsArray($items);
        $this->assertArrayNotHasKey('data', $items);
        $response->assertJsonMissingPath('data.items.data');

        $listed = collect($items)->firstWhere('id', $regular->id);
        $this->assertNotNull($listed);
        $this->assertSame('free', $listed['plan']);
    }

    public function test_admin_user_list_excludes_administrators(): void
    {
        $adminUser = $this->createAdminUser(['email' => 'admin-only@example.com']);
        $adminViewer = $this->createAdminUser(['email' => 'viewer@example.com']);
        $this->actingAsAdmin($adminViewer);

        $response = $this->getJson('/api/v1/admin/users')->assertOk();

        $emails = collect($response->json('data.items'))->pluck('email');
        $this->assertFalse($emails->contains('admin-only@example.com'));
        $this->assertFalse($emails->contains('viewer@example.com'));
    }
}
