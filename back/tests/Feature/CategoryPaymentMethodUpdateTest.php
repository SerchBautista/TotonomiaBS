<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Category;
use App\Models\OtherPaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class CategoryPaymentMethodUpdateTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    // ─── Category update ───────────────────────────────────────────────────────

    public function test_workspace_admin_can_update_category(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}", [
            'name' => 'Updated Name',
            'icon' => 'star',
            'color' => '#ff0000',
        ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name', 'icon' => 'star', 'color' => '#ff0000']);

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Updated Name']);
    }

    public function test_viewer_cannot_update_category(): void
    {
        ['workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();
        $viewer = User::factory()->create();
        $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
        $this->actingAsUser($viewer);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}", [
            'name' => 'Hack',
        ])->assertForbidden();
    }

    public function test_cannot_update_category_owned_by_another_user(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $otherCategory = Category::factory()->create(); // owned by a different user
        $this->actingAsUser($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/categories/{$otherCategory->id}", [
            'name' => 'Hacked',
        ])->assertForbidden();
    }

    public function test_update_category_validates_color_format(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}", [
            'color' => 'not-a-color',
        ])->assertUnprocessable();
    }

    public function test_non_member_cannot_update_category(): void
    {
        ['workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();
        $stranger = User::factory()->create();
        $this->actingAsUser($stranger);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/categories/{$category->id}", [
            'name' => 'Stranger',
        ])->assertForbidden();
    }

    // ─── Card update ────────────────────────────────────────────────────────────

    public function test_workspace_admin_can_update_card(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'card' => $card] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/cards/{$card->id}", [
            'name' => 'Updated Card',
            'card_type' => 'credit',
        ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated Card', 'card_type' => 'credit']);

        $this->assertDatabaseHas('cards', ['id' => $card->id, 'name' => 'Updated Card']);
    }

    public function test_viewer_cannot_update_card(): void
    {
        ['workspace' => $workspace, 'card' => $card] = $this->createUserWithWorkspace();
        $viewer = User::factory()->create();
        $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
        $this->actingAsUser($viewer);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/cards/{$card->id}", [
            'name' => 'Hack',
        ])->assertForbidden();
    }

    public function test_cannot_update_card_from_another_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        ['card' => $otherCard] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/cards/{$otherCard->id}", [
            'name' => 'Cross-tenant hack',
        ])->assertForbidden();
    }

    public function test_update_card_validates_card_type(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'card' => $card] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/cards/{$card->id}", [
            'card_type' => 'prepaid',
        ])->assertUnprocessable();
    }

    public function test_non_member_cannot_update_card(): void
    {
        ['workspace' => $workspace, 'card' => $card] = $this->createUserWithWorkspace();
        $stranger = User::factory()->create();
        $this->actingAsUser($stranger);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/cards/{$card->id}", [
            'name' => 'Stranger',
        ])->assertForbidden();
    }

    public function test_last_4_digits_is_encrypted_at_rest(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();
        $card = \App\Models\Card::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $workspace->owner_id,
            'last_4_digits' => '4242',
        ]);

        $raw = \Illuminate\Support\Facades\DB::table('cards')
            ->where('id', $card->id)
            ->value('last_4_digits');

        $this->assertNotEquals('4242', $raw, 'last_4_digits must not be stored in plaintext');
        $this->assertEquals('4242', $card->last_4_digits, 'Model should return decrypted value');
    }

    public function test_card_resource_masks_last_4_digits(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $card = \App\Models\Card::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'last_4_digits' => '9876',
        ]);
        $card->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);
        $this->actingAsUser($user);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/cards")
            ->assertOk()
            ->assertJsonFragment(['last_4_digits' => '****9876']);
    }

    // ─── OtherPaymentMethod update ─────────────────────────────────────────────

    public function test_workspace_admin_can_update_other_payment_method(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $method = OtherPaymentMethod::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);
        $this->actingAsUser($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/other-payment-methods/{$method->id}", [
            'name' => 'Updated Method',
        ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated Method']);

        $this->assertDatabaseHas('other_payment_methods', ['id' => $method->id, 'name' => 'Updated Method']);
    }

    public function test_viewer_cannot_update_other_payment_method(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();
        $method = OtherPaymentMethod::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $workspace->owner_id]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);
        $viewer = User::factory()->create();
        $workspace->members()->attach($viewer->id, ['role' => 'viewer']);
        $this->actingAsUser($viewer);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/other-payment-methods/{$method->id}", [
            'name' => 'Hack',
        ])->assertForbidden();
    }

    public function test_non_member_cannot_update_other_payment_method(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();
        $method = OtherPaymentMethod::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $workspace->owner_id]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);
        $stranger = User::factory()->create();
        $this->actingAsUser($stranger);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/other-payment-methods/{$method->id}", [
            'name' => 'Stranger',
        ])->assertForbidden();
    }
}
