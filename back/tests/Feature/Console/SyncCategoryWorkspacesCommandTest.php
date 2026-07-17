<?php

namespace Tests\Feature\Console;

use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncCategoryWorkspacesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_command_without_flags_is_dry_run_by_default(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $category = Category::factory()->forUser($user)->create();

        $this->artisan('categories:sync-workspaces')
            ->expectsOutputToContain('DRY RUN — no changes will be made.')
            ->expectsOutputToContain('DRY RUN — run with --apply to apply changes.')
            ->assertSuccessful();

        // The pivot is still empty — dry-run does not persist.
        $this->assertSame(0, $category->fresh()->workspaces()->count());

        $this->assertDatabaseMissing('category_workspace', [
            'category_id' => $category->id,
            'workspace_id' => $workspace->id,
        ]);
    }

    public function test_command_with_apply_flag_persists_changes(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $workspace1 = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace2 = Workspace::factory()->create(['owner_id' => $user->id]);
        $category = Category::factory()->forUser($user)->create();

        $this->artisan('categories:sync-workspaces', [
            '--apply' => true,
        ])
            ->expectsOutputToContain("category \"{$category->name}\"")
            ->expectsOutputToContain('Sync complete.')
            ->assertSuccessful();

        $linkedWorkspaces = $category->fresh()->workspaces->pluck('id')->all();
        $this->assertContains($workspace1->id, $linkedWorkspaces);
        $this->assertContains($workspace2->id, $linkedWorkspaces);
    }

    public function test_command_with_dry_run_flag_is_dry_run(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $category = Category::factory()->forUser($user)->create();

        $this->assertSame(0, $category->workspaces()->count());

        $this->artisan('categories:sync-workspaces', [
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('DRY RUN')
            ->assertSuccessful();

        $this->assertSame(0, $category->fresh()->workspaces()->count());

        $this->assertDatabaseMissing('category_workspace', [
            'category_id' => $category->id,
            'workspace_id' => $workspace->id,
        ]);
    }

    public function test_command_links_existing_categories_to_owned_workspaces(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $workspace1 = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace2 = Workspace::factory()->create(['owner_id' => $user->id]);

        $category = Category::factory()->forUser($user)->create();
        // No link to any workspace yet.

        $this->artisan('categories:sync-workspaces', [
            '--apply' => true,
        ])
            ->expectsOutputToContain("category \"{$category->name}\"")
            ->expectsOutputToContain('Sync complete.')
            ->assertSuccessful();

        $linkedWorkspaces = $category->fresh()->workspaces->pluck('id')->all();
        $this->assertContains($workspace1->id, $linkedWorkspaces);
        $this->assertContains($workspace2->id, $linkedWorkspaces);
    }

    public function test_command_is_idempotent_when_run_twice(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $category = Category::factory()->forUser($user)->create();

        $this->artisan('categories:sync-workspaces', ['--apply' => true])->assertSuccessful();
        $this->assertSame(1, $category->fresh()->workspaces()->count());

        // Running again must not add a second pivot row.
        $this->artisan('categories:sync-workspaces', ['--apply' => true])->assertSuccessful();
        $this->assertSame(1, $category->fresh()->workspaces()->count());
    }

    public function test_command_only_targets_specific_user_when_user_filter_is_provided(): void
    {
        $target = User::factory()->create();
        $target->assignRole('user');
        $other = User::factory()->create();
        $other->assignRole('user');

        $targetWorkspace = Workspace::factory()->create(['owner_id' => $target->id]);
        $otherWorkspace = Workspace::factory()->create(['owner_id' => $other->id]);

        $targetCategory = Category::factory()->forUser($target)->create();
        $otherCategory = Category::factory()->forUser($other)->create();

        $this->artisan('categories:sync-workspaces', [
            '--user' => $target->id,
            '--apply' => true,
        ])->assertSuccessful();

        // The target's category is linked.
        $this->assertDatabaseHas('category_workspace', [
            'category_id' => $targetCategory->id,
            'workspace_id' => $targetWorkspace->id,
        ]);

        // The other user's category is untouched.
        $this->assertDatabaseMissing('category_workspace', [
            'category_id' => $otherCategory->id,
            'workspace_id' => $otherWorkspace->id,
        ]);
    }

    public function test_command_skips_users_with_no_categories_or_owned_workspaces(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        // No categories, no workspaces.

        $this->artisan('categories:sync-workspaces')
            ->expectsOutputToContain('No users with categories and owned workspaces found.')
            ->assertSuccessful();
    }

    public function test_command_reports_already_existing_links_as_skipped(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $category = Category::factory()->forUser($user)->create();
        $category->workspaces()->attach($workspace->id);

        $this->artisan('categories:sync-workspaces', ['--apply' => true])
            ->doesntExpectOutputToContain("category \"{$category->name}\"")
            ->assertSuccessful();

        // Still exactly one link.
        $this->assertSame(1, $category->fresh()->workspaces()->count());
    }
}
