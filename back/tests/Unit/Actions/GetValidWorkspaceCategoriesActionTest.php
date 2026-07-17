<?php

namespace Tests\Unit\Actions;

use App\Actions\GetValidWorkspaceCategoriesAction;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetValidWorkspaceCategoriesActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_personal_workspace_returns_only_linked_and_active_categories_owned_by_authenticated_user(): void
    {
        $owner = User::factory()->create();
        $workspace = \App\Models\Workspace::factory()->personal()->create(['owner_id' => $owner->id]);

        $ownerCategoryA = Category::factory()->forUser($owner)->create();
        $ownerCategoryB = Category::factory()->forUser($owner)->create();
        $ownerCategoryA->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);
        $ownerCategoryB->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        $otherUser = User::factory()->create();
        $otherCategory = Category::factory()->forUser($otherUser)->create();
        $otherCategory->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        $ids = app(GetValidWorkspaceCategoriesAction::class)
            ->execute($workspace)
            ->pluck('id')
            ->all();

        $this->assertContains($ownerCategoryA->id, $ids);
        $this->assertContains($ownerCategoryB->id, $ids);
        $this->assertNotContains($otherCategory->id, $ids);
    }

    public function test_shared_workspace_returns_only_categories_linked_to_workspace(): void
    {
        $owner = User::factory()->create();
        $workspace = \App\Models\Workspace::factory()->create(['owner_id' => $owner->id, 'type' => 'familiar']);

        $linkedCategory = Category::factory()->forUser($owner)->create();
        $linkedCategory->workspaces()->attach($workspace->id);

        $notLinkedCategory = Category::factory()->forUser($owner)->create();

        $ids = app(GetValidWorkspaceCategoriesAction::class)
            ->execute($workspace)
            ->pluck('id')
            ->all();

        $this->assertContains($linkedCategory->id, $ids);
        $this->assertNotContains($notLinkedCategory->id, $ids);
    }
}
