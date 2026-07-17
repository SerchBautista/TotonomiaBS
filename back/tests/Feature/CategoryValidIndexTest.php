<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class CategoryValidIndexTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_personal_workspace_valid_categories_include_only_linked_and_active_categories(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $linkedCategory] = $this->createUserWithWorkspace(['type' => 'personal']);
        $unlinkedCategory = Category::factory()->forUser($user)->create();

        $this->actingAsUser($user);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/categories/valid")
            ->assertOk();

        $ids = array_column($response->json('data'), 'id');

        $this->assertContains($linkedCategory->id, $ids);
        $this->assertNotContains($unlinkedCategory->id, $ids);
    }

    public function test_shared_workspace_valid_categories_include_only_linked_categories(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace(['type' => 'familiar']);

        $linkedCategory = Category::factory()->forUser($owner)->create();
        $linkedCategory->workspaces()->attach($workspace->id);

        $unlinkedCategory = Category::factory()->forUser($owner)->create();

        $member = User::factory()->create();
        $member->assignRole('user');
        $workspace->members()->attach($member->id, ['role' => 'viewer']);

        $this->actingAsUser($member);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/categories/valid")
            ->assertOk();

        $ids = array_column($response->json('data'), 'id');

        $this->assertContains($linkedCategory->id, $ids);
        $this->assertNotContains($unlinkedCategory->id, $ids);
    }

    public function test_non_member_cannot_access_valid_categories_endpoint(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();
        $outsider = User::factory()->create();
        $outsider->assignRole('user');

        $this->actingAsUser($outsider);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/categories/valid")
            ->assertForbidden();
    }

    public function test_unlinked_category_with_usage_is_not_valid_for_new_movements(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace(['type' => 'familiar']);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
        ]);

        $category->workspaces()->updateExistingPivot($workspace->id, [
            'is_shared' => false,
            'is_active' => false,
        ]);

        $this->actingAsUser($owner);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/categories/valid")
            ->assertOk();

        $ids = array_column($response->json('data'), 'id');

        $this->assertNotContains($category->id, $ids);
    }
}
