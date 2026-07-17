<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class CardWorkspaceLinkTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_store_card_links_additional_workspaces(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $secondWorkspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $secondWorkspace->members()->attach($user->id, ['role' => 'owner']);
        $this->actingAsUser($user);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/cards", [
            'name' => 'Shared Card',
            'card_type' => 'credit',
            'last_4_digits' => '4242',
            'workspace_ids' => [$secondWorkspace->id],
        ])->assertCreated();

        $cardId = $response->json('data.id');

        $this->assertDatabaseHas('card_workspace', [
            'card_id' => $cardId,
            'workspace_id' => $workspace->id,
            'is_shared' => true,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('card_workspace', [
            'card_id' => $cardId,
            'workspace_id' => $secondWorkspace->id,
            'is_shared' => true,
            'is_active' => true,
        ]);
    }

    public function test_store_card_rejects_foreign_additional_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $stranger = User::factory()->create();
        $stranger->assignRole('user');
        $foreignWorkspace = Workspace::factory()->create(['owner_id' => $stranger->id]);
        $this->actingAsUser($user);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/cards", [
            'name' => 'Shared Card',
            'card_type' => 'credit',
            'last_4_digits' => '4242',
            'workspace_ids' => [$foreignWorkspace->id],
        ])
            ->assertForbidden()
            ->assertJsonPath('status', 403)
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_update_card_syncs_workspaces_preserving_url_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $secondWorkspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $secondWorkspace->members()->attach($user->id, ['role' => 'owner']);
        $card = Card::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
        $card->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);
        $this->actingAsUser($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/cards/{$card->id}", [
            'workspace_ids' => [$secondWorkspace->id],
        ])->assertOk();

        $this->assertDatabaseHas('card_workspace', [
            'card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'is_shared' => true,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('card_workspace', [
            'card_id' => $card->id,
            'workspace_id' => $secondWorkspace->id,
            'is_shared' => true,
            'is_active' => true,
        ]);
    }

    public function test_update_card_keeps_read_only_workspace_when_removed_and_in_use(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $secondWorkspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $secondWorkspace->members()->attach($user->id, ['role' => 'owner']);

        $card = Card::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
        $card->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);
        $card->workspaces()->attach($secondWorkspace->id, ['is_shared' => true, 'is_active' => true]);

        Expense::factory()->create([
            'workspace_id' => $secondWorkspace->id,
            'user_id' => $user->id,
            'category_id' => Category::factory()->forUser($user)->create()->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $this->actingAsUser($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/cards/{$card->id}", [
            'workspace_ids' => [],
        ])->assertOk();

        $this->assertDatabaseHas('card_workspace', [
            'card_id' => $card->id,
            'workspace_id' => $secondWorkspace->id,
            'is_shared' => false,
            'is_active' => false,
        ]);
    }
}
