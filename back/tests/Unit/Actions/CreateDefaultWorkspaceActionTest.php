<?php

namespace Tests\Unit\Actions;

use App\Actions\CreateDefaultWorkspaceAction;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateDefaultWorkspaceActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    private function makeUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return $user;
    }

    public function test_creates_general_workspace_owned_by_user(): void
    {
        $user = $this->makeUser();

        app(CreateDefaultWorkspaceAction::class)->execute($user);

        $this->assertDatabaseHas('workspaces', [
            'owner_id' => $user->id,
            'name' => 'General',
            'type' => 'personal',
            'currency_code' => 'MXN',
        ]);
    }

    /**
     * Regression: the default workspace must insert the owner into the
     * workspace_user pivot with role 'owner' (not 'admin'). Otherwise
     * BudgetPolicy::create rejects the owner with 403.
     */
    public function test_attach_owner_to_workspace_user_pivot_with_role_owner(): void
    {
        $user = $this->makeUser();

        app(CreateDefaultWorkspaceAction::class)->execute($user);

        $workspace = Workspace::where('owner_id', $user->id)->where('name', 'General')->first();

        $this->assertDatabaseHas('workspace_user', [
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
    }

    public function test_creates_general_category_for_user(): void
    {
        $user = $this->makeUser();

        app(CreateDefaultWorkspaceAction::class)->execute($user);

        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'General',
        ]);
    }

    public function test_category_is_assigned_to_workspace_via_pivot(): void
    {
        $user = $this->makeUser();

        app(CreateDefaultWorkspaceAction::class)->execute($user);

        $workspace = Workspace::where('owner_id', $user->id)->where('name', 'General')->first();
        $category = $user->categories()->where('name', 'General')->first();

        $this->assertDatabaseHas('category_workspace', [
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_sets_default_workspace_id_on_user(): void
    {
        $user = $this->makeUser();

        app(CreateDefaultWorkspaceAction::class)->execute($user);

        $workspace = Workspace::where('owner_id', $user->id)->where('name', 'General')->first();
        $this->assertEquals($workspace->id, $user->fresh()->default_workspace_id);
    }
}
