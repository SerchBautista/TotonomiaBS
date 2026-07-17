<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class CardDestroyTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_owner_can_delete_card(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $card = $workspace->cards->first();
        $cardId = $card->id;

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/cards/{$cardId}")
            ->assertNoContent();

        $this->assertSoftDeleted('cards', ['id' => $cardId]);
    }

    public function test_destroy_card_with_linked_expenses_returns_409_card_in_use(): void
    {
        // H-010 fix: deleting a card that still has expenses (or fixed
        // expenses) linked must be rejected with 409 `card_in_use` instead
        // of silently soft-deleting it.
        ['user' => $user, 'workspace' => $workspace, 'category' => $category, 'card' => $card] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '50.00',
        ]);

        $response = $this->deleteJson("/api/v1/workspaces/{$workspace->id}/cards/{$card->id}");

        $response->assertStatus(409)
            ->assertJsonPath('status', 409)
            ->assertJsonPath('code', 'card_in_use');

        $this->assertNotSoftDeleted('cards', ['id' => $card->id]);
    }

    public function test_non_owner_member_cannot_delete_card(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();

        $member = User::factory()->create();
        $member->assignRole('user');
        $workspace->members()->attach($member->id, ['role' => 'viewer']);

        $this->actingAsUser($member);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/cards/{$workspace->cards->first()->id}")
            ->assertForbidden();
    }

    public function test_non_member_cannot_delete_card(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();

        $outsider = User::factory()->create();
        $outsider->assignRole('user');
        $this->actingAsUser($outsider);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/cards/{$workspace->cards->first()->id}")
            ->assertForbidden();
    }

    public function test_destroy_nonexistent_card_returns_404(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/cards/00000000-0000-0000-0000-000000000000")
            ->assertNotFound();
    }
}
