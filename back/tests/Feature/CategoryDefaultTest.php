<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class CategoryDefaultTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'premium', 'guard_name' => 'api']);
    }

    public function test_set_default_activates_category_and_deactivates_previous(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();

        $previous = Category::factory()->forUser($owner)->create(['is_default' => true]);
        $previous->workspaces()->attach($workspace->id);
        $category = Category::factory()->forUser($owner)->create(['is_default' => false]);
        $category->workspaces()->attach($workspace->id);

        $this->actingAsUser($owner);

        $response = $this->patchJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}/default");

        $response->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'is_default' => true]);
        $this->assertDatabaseHas('categories', ['id' => $previous->id, 'is_default' => false]);
    }

    public function test_toggle_on_already_default_category_unmarks_it(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();
        $category->forceFill(['is_default' => true])->save();

        $this->actingAsUser($owner);

        $response = $this->patchJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}/default");

        $response->assertOk()
            ->assertJsonPath('data.is_default', false);

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'is_default' => false]);
    }

    public function test_non_owner_member_receives_403(): void
    {
        ['workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();

        $member = User::factory()->create();
        $member->assignRole('user');
        $workspace->members()->attach($member->id, ['role' => 'viewer']);

        $this->actingAsUser($member);

        $response = $this->patchJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}/default");

        $response->assertForbidden();
    }
}
