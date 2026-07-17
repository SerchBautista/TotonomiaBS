<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class CategoryOwnershipTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_owner_can_create_category(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/categories", [
            'name' => 'Groceries',
            'color' => '#ff0000',
        ])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'Groceries', 'user_id' => $user->id]);

        $this->assertDatabaseHas('categories', ['name' => 'Groceries', 'user_id' => $user->id]);
    }

    public function test_creating_category_auto_enables_it_in_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/categories", [
            'name' => 'Auto-enabled',
        ])->assertCreated();

        $categoryId = $response->json('data.id');

        $this->assertDatabaseHas('category_workspace', [
            'category_id' => $categoryId,
            'workspace_id' => $workspace->id,
        ]);
    }

    public function test_member_cannot_create_category(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();
        $member = User::factory()->create();
        $member->assignRole('user');
        $workspace->members()->attach($member->id, ['role' => 'viewer']);
        $this->actingAsUser($member);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/categories", [
            'name' => 'Unauthorized',
        ])->assertForbidden();
    }

    public function test_non_member_cannot_create_category(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();
        $stranger = User::factory()->create();
        $stranger->assignRole('user');
        $this->actingAsUser($stranger);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/categories", [
            'name' => 'Stranger',
        ])->assertForbidden();
    }

    public function test_only_category_owner_can_delete_it(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();
        $otherUser = User::factory()->create();
        $otherUser->assignRole('user');
        $workspace->members()->attach($otherUser->id, ['role' => 'admin']);
        $category = Category::factory()->forUser($otherUser)->create();
        $category->workspaces()->attach($workspace->id);

        // workspace owner cannot delete another user's category
        ['user' => $owner] = $this->createUserWithWorkspace();
        $this->actingAsUser($owner);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}")
            ->assertForbidden();
    }

    public function test_category_owner_can_delete_own_category(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }
}
