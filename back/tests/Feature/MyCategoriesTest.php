<?php

namespace Tests\Feature;

use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class MyCategoriesTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_my_categories_lists_only_current_shared_workspaces_in_summary(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace(['type' => 'familiar']);

        $sharedInactiveWorkspace = Workspace::factory()->create([
            'owner_id' => $owner->id,
            'type' => 'personal',
        ]);
        $sharedInactiveWorkspace->members()->attach($owner->id, ['role' => 'admin']);
        $category->workspaces()->attach($sharedInactiveWorkspace->id, ['is_shared' => true, 'is_active' => false]);

        $historicalWorkspace = Workspace::factory()->create([
            'owner_id' => $owner->id,
            'type' => 'empresa',
        ]);
        $historicalWorkspace->members()->attach($owner->id, ['role' => 'admin']);
        $category->workspaces()->attach($historicalWorkspace->id, ['is_shared' => false, 'is_active' => false]);

        $this->actingAsUser($owner);

        $response = $this->getJson('/api/v1/user/categories')->assertOk();

        $item = collect($response->json('data'))->firstWhere('id', $category->id);
        $linkedWorkspaceIds = collect($item['linked_workspaces'])->pluck('id')->all();

        $this->assertSame(2, $item['linked_workspaces_count']);
        $this->assertCount(2, $item['linked_workspaces']);
        $this->assertContains($workspace->id, $linkedWorkspaceIds);
        $this->assertContains($sharedInactiveWorkspace->id, $linkedWorkspaceIds);
        $this->assertNotContains($historicalWorkspace->id, $linkedWorkspaceIds);
    }
}
