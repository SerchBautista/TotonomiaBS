<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class CategoryAssignmentTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_owner_can_assign_own_category_to_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $category = Category::factory()->forUser($user)->create();
        $this->actingAsUser($user);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}/assign")
            ->assertNoContent();

        $this->assertDatabaseHas('category_workspace', [
            'category_id' => $category->id,
            'workspace_id' => $workspace->id,
        ]);
    }

    public function test_owner_can_unassign_category_from_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}/assign")
            ->assertNoContent();

        $this->assertDatabaseMissing('category_workspace', [
            'category_id' => $category->id,
            'workspace_id' => $workspace->id,
        ]);
    }

    public function test_member_cannot_assign_category(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $member = User::factory()->create();
        $member->assignRole('user');
        $workspace->members()->attach($member->id, ['role' => 'viewer']);
        $category = Category::factory()->forUser($owner)->create();
        $this->actingAsUser($member);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}/assign")
            ->assertForbidden();
    }

    public function test_member_cannot_unassign_category(): void
    {
        ['workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();
        $member = User::factory()->create();
        $member->assignRole('user');
        $workspace->members()->attach($member->id, ['role' => 'viewer']);
        $this->actingAsUser($member);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}/assign")
            ->assertForbidden();
    }

    public function test_assign_is_idempotent(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}/assign")
            ->assertNoContent();

        // second assign should not fail
        $this->postJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}/assign")
            ->assertNoContent();

        $this->assertDatabaseCount('category_workspace', 1);
    }

    public function test_owner_cannot_assign_category_not_owned_by_workspace_owner(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $otherUser = User::factory()->create();
        $otherUser->assignRole('user');
        $foreignCategory = Category::factory()->forUser($otherUser)->create();
        $this->actingAsUser($owner);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/categories/{$foreignCategory->id}/assign")
            ->assertUnprocessable();

        $this->assertDatabaseMissing('category_workspace', [
            'category_id' => $foreignCategory->id,
            'workspace_id' => $workspace->id,
        ]);
    }

    public function test_owner_cannot_unassign_category_not_owned_by_workspace_owner(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $otherUser = User::factory()->create();
        $otherUser->assignRole('user');
        $foreignCategory = Category::factory()->forUser($otherUser)->create();
        $workspace->enabledCategories()->attach($foreignCategory->id);
        $this->actingAsUser($owner);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/categories/{$foreignCategory->id}/assign")
            ->assertUnprocessable();

        $this->assertDatabaseHas('category_workspace', [
            'category_id' => $foreignCategory->id,
            'workspace_id' => $workspace->id,
        ]);
    }
}
