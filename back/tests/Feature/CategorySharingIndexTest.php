<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class CategorySharingIndexTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_owner_can_list_workspace_category_sharing(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();
        $this->actingAsUser($owner);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/categories/sharing");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'name',
                    'is_linked',
                    'is_active_in_workspace',
                    'is_in_use_in_workspace',
                    'is_valid_for_transactions',
                    'state',
                ]],
            ]);

        $items = $response->json('data');
        $this->assertNotEmpty($items);

        $item = collect($items)->firstWhere('id', $category->id);
        $this->assertNotNull($item);
        $this->assertTrue($item['is_linked']);
    }

    public function test_non_owner_member_cannot_list_workspace_category_sharing(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();

        $member = User::factory()->create();
        $member->assignRole('user');
        $workspace->members()->attach($member->id, ['role' => 'viewer']);

        $this->actingAsUser($member);

        // Categories sharing endpoint is gated by `workspace.can_manage_categories`,
        // which restricts access to owners only.
        $this->getJson("/api/v1/workspaces/{$workspace->id}/categories/sharing")
            ->assertForbidden();
    }

    public function test_category_sharing_for_missing_workspace_returns_404(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->getJson('/api/v1/workspaces/00000000-0000-0000-0000-000000000000/categories/sharing')
            ->assertNotFound();
    }
}
