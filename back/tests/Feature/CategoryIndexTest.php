<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class CategoryIndexTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_owner_sees_workspace_category_management_flags(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();

        $linked = Category::factory()->forUser($user)->create();
        $linked->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        $readOnlyLinked = Category::factory()->forUser($user)->create();
        $readOnlyLinked->workspaces()->attach($workspace->id, ['is_shared' => false, 'is_active' => false]);

        $unlinked = Category::factory()->forUser($user)->create();

        $this->actingAsUser($user);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/categories")
            ->assertOk();

        $data = $response->json('data');
        $ids = array_column($data, 'id');

        $this->assertContains($linked->id, $ids);
        $this->assertContains($readOnlyLinked->id, $ids);
        $this->assertContains($unlinked->id, $ids);

        $linkedItem = collect($data)->firstWhere('id', $linked->id);
        $readOnlyLinkedItem = collect($data)->firstWhere('id', $readOnlyLinked->id);
        $unlinkedItem = collect($data)->firstWhere('id', $unlinked->id);

        $this->assertTrue($linkedItem['is_linked']);
        $this->assertTrue($linkedItem['is_active_in_workspace']);
        $this->assertTrue($linkedItem['is_valid_for_transactions']);
        $this->assertSame('linked', $linkedItem['state']);

        $this->assertFalse($readOnlyLinkedItem['is_linked']);
        $this->assertFalse($readOnlyLinkedItem['is_active_in_workspace']);
        $this->assertFalse($readOnlyLinkedItem['is_valid_for_transactions']);
        $this->assertSame('read_only_linked', $readOnlyLinkedItem['state']);

        $this->assertFalse($unlinkedItem['is_linked']);
        $this->assertFalse($unlinkedItem['is_active_in_workspace']);
        $this->assertFalse($unlinkedItem['is_valid_for_transactions']);
        $this->assertSame('not_linked', $unlinkedItem['state']);
    }

    public function test_member_cannot_access_workspace_categories_management_index(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();

        $member = User::factory()->create();
        $member->assignRole('user');
        $workspace->members()->attach($member->id, ['role' => 'viewer']);

        $this->actingAsUser($member);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/categories")
            ->assertForbidden()
            ->assertJsonPath('status', 403)
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_non_member_cannot_list_categories(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();
        $stranger = User::factory()->create();
        $stranger->assignRole('user');
        $this->actingAsUser($stranger);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/categories")
            ->assertForbidden()
            ->assertJsonPath('status', 403)
            ->assertJsonPath('code', 'forbidden');
    }
}
