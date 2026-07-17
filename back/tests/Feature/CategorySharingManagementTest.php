<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class CategorySharingManagementTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_unlink_category_with_usage_keeps_inactive_pivot_row(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $this->actingAsUser($owner);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
        ]);

        $this->patchJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}/link", [
            'is_linked' => false,
        ])->assertNoContent();

        $this->assertDatabaseHas('category_workspace', [
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'is_shared' => false,
            'is_active' => false,
        ]);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/categories")
            ->assertOk();

        $categoryItem = collect($response->json('data'))->firstWhere('id', $category->id);

        $this->assertFalse($categoryItem['is_linked']);
        $this->assertFalse($categoryItem['is_active_in_workspace']);
        $this->assertTrue($categoryItem['is_in_use_in_workspace']);
        $this->assertFalse($categoryItem['is_valid_for_transactions']);
        $this->assertSame('read_only_linked', $categoryItem['state']);
    }

    public function test_unlink_category_without_usage_removes_pivot_row(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $this->actingAsUser($owner);

        $this->patchJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}/link", [
            'is_linked' => false,
        ])->assertNoContent();

        $this->assertDatabaseMissing('category_workspace', [
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_relink_category_from_inactive_state_reactivates_pivot_row(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $this->actingAsUser($owner);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
        ]);

        $this->patchJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}/link", [
            'is_linked' => false,
        ])->assertNoContent();

        $this->patchJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}/link", [
            'is_linked' => true,
        ])->assertNoContent();

        $this->assertDatabaseHas('category_workspace', [
            'workspace_id' => $workspace->id,
            'category_id' => $category->id,
            'is_shared' => true,
            'is_active' => true,
        ]);
    }

    public function test_unlinked_category_with_usage_is_excluded_from_valid_categories_endpoint(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $this->actingAsUser($owner);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
        ]);

        $this->patchJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}/link", [
            'is_linked' => false,
        ])->assertNoContent();

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/categories/valid")
            ->assertOk();

        $ids = array_column($response->json('data'), 'id');

        $this->assertNotContains($category->id, $ids);
    }

    public function test_bulk_unlink_all_applies_lifecycle_with_and_without_usage(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $usedCategory] = $this->createUserWithWorkspace(['type' => 'empresa']);
        $this->actingAsUser($owner);

        $freeCategory = Category::factory()->forUser($owner)->create();
        $freeCategory->workspaces()->attach($workspace->id);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $usedCategory->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
        ]);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/categories/link-bulk", [
            'operation' => 'unlink_all',
        ])->assertOk()
            ->assertJsonStructure([
                'operation',
                'total',
                'processed',
                'blocked',
                'processed_category_ids',
                'blocked_category_ids',
            ]);

        $response
            ->assertJsonPath('operation', 'unlink_all')
            ->assertJsonPath('processed', 2)
            ->assertJsonPath('blocked', 0);

        $this->assertContains($freeCategory->id, $response->json('processed_category_ids'));
        $this->assertContains($usedCategory->id, $response->json('processed_category_ids'));
        $this->assertSame([], $response->json('blocked_category_ids'));

        $this->assertDatabaseMissing('category_workspace', [
            'workspace_id' => $workspace->id,
            'category_id' => $freeCategory->id,
        ]);

        $this->assertDatabaseHas('category_workspace', [
            'workspace_id' => $workspace->id,
            'category_id' => $usedCategory->id,
            'is_shared' => false,
            'is_active' => false,
        ]);
    }

    public function test_non_owner_member_cannot_manage_workspace_category_endpoints(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $member = User::factory()->create();
        $member->assignRole('user');
        $workspace->members()->attach($member->id, ['role' => 'guest', 'can_add_categories' => false]);

        $this->actingAsUser($member);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/categories")
            ->assertForbidden();

        $this->patchJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}/link", [
            'is_linked' => true,
        ])->assertForbidden();

        $this->postJson("/api/v1/workspaces/{$workspace->id}/categories/link-bulk", [
            'operation' => 'link_all',
        ])->assertForbidden();

        $this->postJson("/api/v1/workspaces/{$workspace->id}/categories", [
            'name' => 'Nueva',
            'icon' => 'wallet',
            'color' => '#FFFFFF',
        ])->assertForbidden();
    }

    public function test_bulk_sharing_rejects_invalid_operation_with_422(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $this->actingAsUser($owner);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/categories/link-bulk", [
            'operation' => 'invalid_operation',
        ])->assertUnprocessable()
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonPath('fieldErrors.operation.0', 'The selected operation is invalid.');
    }

    public function test_create_category_from_workspace_creates_and_links_category_atomically(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $this->actingAsUser($owner);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/categories", [
            'name' => 'Suscripciones',
            'icon' => 'wallet',
            'color' => '#123456',
        ])->assertCreated();

        $categoryId = $response->json('data.id');

        $this->assertDatabaseHas('categories', [
            'id' => $categoryId,
            'user_id' => $owner->id,
        ]);

        $this->assertDatabaseHas('category_workspace', [
            'workspace_id' => $workspace->id,
            'category_id' => $categoryId,
            'is_shared' => true,
            'is_active' => true,
        ]);
    }

    public function test_delete_category_returns_conflict_when_in_use(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();
        $this->actingAsUser($owner);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'payment_type' => 'cash',
            'payment_instrument_id' => null,
        ]);

        $this->withHeader('X-Request-Id', 'req-category-delete-conflict-409')
            ->deleteJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}")
            ->assertStatus(409)
            ->assertJsonPath('status', 409)
            ->assertJsonPath('code', 'category_in_use')
            ->assertJsonPath('message', __('api.errors.category_in_use'))
            ->assertJsonPath('request_id', 'req-category-delete-conflict-409');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'deleted_at' => null]);
    }

    public function test_update_activation_returns_conflict_when_category_is_not_shared(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();
        $this->actingAsUser($owner);

        $this->patchJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}/link", [
            'is_linked' => false,
        ])->assertNoContent();

        $this->withHeader('X-Request-Id', 'req-category-activation-conflict-409')
            ->patchJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}/activation", [
                'is_active' => true,
            ])
            ->assertStatus(409)
            ->assertJsonPath('status', 409)
            ->assertJsonPath('code', 'category_not_shared_in_workspace')
            ->assertJsonPath('message', __('api.errors.category_not_shared_in_workspace'))
            ->assertJsonPath('request_id', 'req-category-activation-conflict-409');
    }
}
