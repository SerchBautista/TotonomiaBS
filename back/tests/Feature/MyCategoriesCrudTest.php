<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class MyCategoriesCrudTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_authenticated_user_can_create_personal_category(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $response = $this->postJson('/api/v1/user/categories', [
            'name' => 'Personal Groceries',
            'icon' => 'food',
            'color' => '#FF5733',
        ])->assertCreated();

        $categoryId = $response->json('data.id');

        $this->assertDatabaseHas('categories', [
            'id' => $categoryId,
            'user_id' => $user->id,
            'name' => 'Personal Groceries',
            'icon' => 'food',
            'color' => '#FF5733',
        ]);
    }

    public function test_create_personal_category_rejects_invalid_color(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->postJson('/api/v1/user/categories', [
            'name' => 'Bad color',
            'color' => 'red',
        ])
            ->assertStatus(422)
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonValidationErrors(['color'], 'fieldErrors');
    }

    public function test_create_personal_category_requires_name(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->postJson('/api/v1/user/categories', [])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonValidationErrors(['name'], 'fieldErrors');
    }

    public function test_unauthenticated_user_cannot_create_personal_category(): void
    {
        $this->postJson('/api/v1/user/categories', [
            'name' => 'Stranger',
        ])->assertStatus(401);
    }

    public function test_user_can_update_own_personal_category(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $category = Category::factory()->forUser($user)->create(['name' => 'Old Name']);
        $this->actingAsUser($user);

        $this->putJson("/api/v1/user/categories/{$category->id}", [
            'name' => 'New Name',
            'color' => '#00AAFF',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.color', '#00AAFF');

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'New Name',
            'color' => '#00AAFF',
        ]);
    }

    public function test_user_cannot_update_another_users_personal_category(): void
    {
        ['user' => $owner] = $this->createUserWithWorkspace();
        $stranger = User::factory()->create();
        $stranger->assignRole('user');
        $category = Category::factory()->forUser($stranger)->create();

        $this->actingAsUser($owner);

        $this->putJson("/api/v1/user/categories/{$category->id}", [
            'name' => 'Hijacked',
        ])->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_update_personal_category(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $category = Category::factory()->forUser($user)->create();

        $this->putJson("/api/v1/user/categories/{$category->id}", [
            'name' => 'Anon',
        ])->assertStatus(401);
    }

    public function test_user_can_delete_own_personal_category(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $category = Category::factory()->forUser($user)->create();
        $this->actingAsUser($user);

        $this->deleteJson("/api/v1/user/categories/{$category->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_user_cannot_delete_another_users_personal_category(): void
    {
        ['user' => $owner] = $this->createUserWithWorkspace();
        $stranger = User::factory()->create();
        $stranger->assignRole('user');
        $category = Category::factory()->forUser($stranger)->create();

        $this->actingAsUser($owner);

        $this->deleteJson("/api/v1/user/categories/{$category->id}")
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_delete_personal_category(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $category = Category::factory()->forUser($user)->create();

        $this->deleteJson("/api/v1/user/categories/{$category->id}")
            ->assertStatus(401);
    }

    public function test_delete_personal_category_in_use_returns_409(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $category = Category::factory()->forUser($user)->create();
        $category->workspaces()->attach($workspace->id);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
        ]);

        $this->actingAsUser($user);

        $this->withHeader('X-Request-Id', 'req-my-category-delete-conflict-409')
            ->deleteJson("/api/v1/user/categories/{$category->id}")
            ->assertStatus(409)
            ->assertJsonPath('status', 409)
            ->assertJsonPath('code', 'category_in_use')
            ->assertJsonPath('message', __('api.errors.category_in_use'))
            ->assertJsonPath('request_id', 'req-my-category-delete-conflict-409');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'deleted_at' => null]);
    }

    public function test_set_default_personal_category_activates_and_deactivates_previous(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $previous = Category::factory()->forUser($user)->create(['is_default' => true]);
        $category = Category::factory()->forUser($user)->create(['is_default' => false]);

        $this->actingAsUser($user);

        $response = $this->patchJson("/api/v1/user/categories/{$category->id}/default")
            ->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'is_default' => true]);
        $this->assertDatabaseHas('categories', ['id' => $previous->id, 'is_default' => false]);
    }

    public function test_toggle_default_personal_category_unmarks_when_already_default(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $category = Category::factory()->forUser($user)->create(['is_default' => true]);

        $this->actingAsUser($user);

        $this->patchJson("/api/v1/user/categories/{$category->id}/default")
            ->assertOk()
            ->assertJsonPath('data.is_default', false);

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'is_default' => false]);
    }

    public function test_user_cannot_toggle_default_on_another_users_category(): void
    {
        ['user' => $owner] = $this->createUserWithWorkspace();
        $stranger = User::factory()->create();
        $stranger->assignRole('user');
        $category = Category::factory()->forUser($stranger)->create();

        $this->actingAsUser($owner);

        $this->patchJson("/api/v1/user/categories/{$category->id}/default")
            ->assertForbidden();
    }

    /**
     * M-1: POST /api/v1/user/categories must NOT allow mass assignment of
     * `user_id` from the request body, even if the request body includes it.
     * The created category must belong to the authenticated user, never to
     * an attacker-supplied id.
     */
    public function test_create_personal_category_ignores_attacker_supplied_user_id(): void
    {
        ['user' => $owner] = $this->createUserWithWorkspace();
        $stranger = User::factory()->create();

        $this->actingAsUser($owner);

        $response = $this->postJson('/api/v1/user/categories', [
            'name' => 'Hijack attempt',
            'user_id' => $stranger->id,
        ])->assertCreated();

        $categoryId = $response->json('data.id');

        $this->assertDatabaseHas('categories', [
            'id' => $categoryId,
            'user_id' => $owner->id,
            'name' => 'Hijack attempt',
        ]);
        $this->assertDatabaseMissing('categories', [
            'id' => $categoryId,
            'user_id' => $stranger->id,
        ]);
    }

    /**
     * M-1: POST /api/v1/user/categories must NOT allow mass assignment of
     * `is_default` from the request body. The newly created category must
     * always default to `is_default = false`, regardless of the request body.
     */
    public function test_create_personal_category_ignores_attacker_supplied_is_default(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $response = $this->postJson('/api/v1/user/categories', [
            'name' => 'Should not be default',
            'is_default' => true,
        ])->assertCreated();

        $categoryId = $response->json('data.id');

        $this->assertDatabaseHas('categories', [
            'id' => $categoryId,
            'user_id' => $user->id,
            'is_default' => false,
        ]);
    }

    /**
     * M-1: PUT /api/v1/user/categories/{category} must NOT allow mass
     * assignment of `user_id` from the request body.
     */
    public function test_update_personal_category_ignores_attacker_supplied_user_id(): void
    {
        ['user' => $owner] = $this->createUserWithWorkspace();
        $stranger = User::factory()->create();
        $category = Category::factory()->forUser($owner)->create(['name' => 'Original']);

        $this->actingAsUser($owner);

        $this->putJson("/api/v1/user/categories/{$category->id}", [
            'name' => 'Updated',
            'user_id' => $stranger->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.user_id', $owner->id);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'user_id' => $owner->id,
            'name' => 'Updated',
        ]);
    }

    /**
     * L-5: Unverified users must be rejected with 403 `email_not_verified`
     * when calling POST /api/v1/user/categories. This test exercises the
     * write path (as opposed to the read path already covered in
     * LoginVerificationTest), so the unverified user cannot even create
     * their own personal category.
     */
    public function test_unverified_user_cannot_create_personal_category(): void
    {
        $this->seed();

        $user = User::factory()->unverified()->create();
        $user->assignRole('user');
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => 'owner']);
        $user->forceFill(['default_workspace_id' => $workspace->id])->save();

        \Laravel\Passport\Passport::actingAs($user, ['*'], 'api');

        $this->postJson('/api/v1/user/categories', [
            'name' => 'Should be blocked',
        ])
            ->assertStatus(403)
            ->assertJsonPath('status', 403)
            ->assertJsonPath('code', 'email_not_verified');
    }

    public function test_create_personal_category_with_workspace_ids_links_to_workspaces(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $secondWorkspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $secondWorkspace->members()->attach($user->id, ['role' => 'owner']);
        $this->actingAsUser($user);

        $response = $this->postJson('/api/v1/user/categories', [
            'name' => 'Shared Category',
            'workspace_ids' => [$workspace->id, $secondWorkspace->id],
        ])->assertCreated();

        $categoryId = $response->json('data.id');

        $this->assertDatabaseHas('category_workspace', [
            'category_id' => $categoryId,
            'workspace_id' => $workspace->id,
            'is_shared' => true,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('category_workspace', [
            'category_id' => $categoryId,
            'workspace_id' => $secondWorkspace->id,
            'is_shared' => true,
            'is_active' => true,
        ]);
    }

    public function test_create_personal_category_auto_links_when_user_owns_single_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $response = $this->postJson('/api/v1/user/categories', [
            'name' => 'Auto Linked',
        ])->assertCreated();

        $categoryId = $response->json('data.id');

        $this->assertDatabaseHas('category_workspace', [
            'category_id' => $categoryId,
            'workspace_id' => $workspace->id,
            'is_shared' => true,
            'is_active' => true,
        ]);
    }

    public function test_update_personal_category_syncs_workspaces(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $secondWorkspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $secondWorkspace->members()->attach($user->id, ['role' => 'owner']);
        $category = Category::factory()->forUser($user)->create();
        $category->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);
        $this->actingAsUser($user);

        $this->putJson("/api/v1/user/categories/{$category->id}", [
            'workspace_ids' => [$secondWorkspace->id],
        ])->assertOk();

        $this->assertDatabaseMissing('category_workspace', [
            'category_id' => $category->id,
            'workspace_id' => $workspace->id,
        ]);

        $this->assertDatabaseHas('category_workspace', [
            'category_id' => $category->id,
            'workspace_id' => $secondWorkspace->id,
            'is_shared' => true,
            'is_active' => true,
        ]);
    }

    public function test_update_personal_category_rejects_foreign_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $stranger = User::factory()->create();
        $stranger->assignRole('user');
        $foreignWorkspace = Workspace::factory()->create(['owner_id' => $stranger->id]);
        $category = Category::factory()->forUser($user)->create();
        $category->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);
        $this->actingAsUser($user);

        $this->putJson("/api/v1/user/categories/{$category->id}", [
            'workspace_ids' => [$foreignWorkspace->id],
        ])
            ->assertForbidden()
            ->assertJsonPath('status', 403)
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_sync_personal_category_workspaces_response_includes_linked_workspaces_summary(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $secondWorkspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $secondWorkspace->members()->attach($user->id, ['role' => 'owner']);
        $category = Category::factory()->forUser($user)->create();
        $category->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);
        $this->actingAsUser($user);

        $this->patchJson("/api/v1/user/categories/{$category->id}/workspaces", [
            'workspace_ids' => [$workspace->id, $secondWorkspace->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.linked_workspaces_count', 2)
            ->assertJsonCount(2, 'data.linked_workspaces')
            ->assertJsonFragment(['id' => $workspace->id, 'name' => $workspace->name])
            ->assertJsonFragment(['id' => $secondWorkspace->id, 'name' => $secondWorkspace->name]);
    }

    public function test_sync_personal_category_workspaces_keeps_read_only_when_in_use(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
        ]);

        $this->actingAsUser($user);

        $this->patchJson("/api/v1/user/categories/{$category->id}/workspaces", [
            'workspace_ids' => [],
        ])->assertOk();

        $this->assertDatabaseHas('category_workspace', [
            'category_id' => $category->id,
            'workspace_id' => $workspace->id,
            'is_shared' => false,
            'is_active' => false,
        ]);
    }

    public function test_sync_personal_category_workspaces_rejects_foreign_workspace(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $stranger = User::factory()->create();
        $stranger->assignRole('user');
        $foreignWorkspace = Workspace::factory()->create(['owner_id' => $stranger->id]);
        $category = Category::factory()->forUser($user)->create();
        $this->actingAsUser($user);

        $this->patchJson("/api/v1/user/categories/{$category->id}/workspaces", [
            'workspace_ids' => [$foreignWorkspace->id],
        ])
            ->assertForbidden()
            ->assertJsonPath('status', 403)
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_sync_personal_category_workspaces_rejects_legacy_foreign_pivot_without_mutating_it(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();
        $stranger = User::factory()->create();
        $stranger->assignRole('user');
        $foreignWorkspace = Workspace::factory()->create(['owner_id' => $stranger->id]);

        $category->workspaces()->attach($foreignWorkspace->id, ['is_shared' => false, 'is_active' => false]);

        $this->actingAsUser($user);

        $this->patchJson("/api/v1/user/categories/{$category->id}/workspaces", [
            'workspace_ids' => [$workspace->id],
        ])
            ->assertForbidden()
            ->assertJsonPath('status', 403)
            ->assertJsonPath('code', 'forbidden');

        $this->assertDatabaseHas('category_workspace', [
            'category_id' => $category->id,
            'workspace_id' => $foreignWorkspace->id,
            'is_shared' => false,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('category_workspace', [
            'category_id' => $category->id,
            'workspace_id' => $workspace->id,
            'is_shared' => true,
            'is_active' => true,
        ]);
    }
}
