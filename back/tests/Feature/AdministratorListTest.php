<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesAdmin;
use Tests\TestCase;

class AdministratorListTest extends TestCase
{
    use CreatesAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    public function test_administrator_list_returns_items_array_without_nested_data(): void
    {
        $admin = $this->createAdminUser(['name' => 'Listed Admin', 'email' => 'listed-admin@example.com']);
        $viewer = $this->createAdminUser(['email' => 'viewer-admin@example.com']);
        $this->actingAsAdmin($viewer);

        $response = $this->getJson('/api/v1/admin/administrators')
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'items' => [
                        '*' => [
                            'id',
                            'name',
                            'email',
                            'roles',
                        ],
                    ],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $items = $response->json('data.items');
        $this->assertIsArray($items);
        $this->assertArrayNotHasKey('data', $items);
        $response->assertJsonMissingPath('data.items.data');

        $listed = collect($items)->firstWhere('email', 'listed-admin@example.com');
        $this->assertNotNull($listed);
        $this->assertContains('admin', $listed['roles']);
    }
}
