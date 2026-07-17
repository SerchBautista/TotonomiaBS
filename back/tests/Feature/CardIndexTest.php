<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class CardIndexTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_member_can_list_workspace_cards(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        // The trait seeds one card. Add one more to assert the index returns
        // every card linked to the workspace.
        $extra = Card::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
        $extra->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/cards");

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'workspace_id', 'name', 'card_type', 'is_default']]])
            ->assertJsonCount(2, 'data');
    }

    public function test_non_member_cannot_list_workspace_cards(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();

        $outsider = User::factory()->create();
        $outsider->assignRole('user');
        $this->actingAsUser($outsider);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/cards")
            ->assertForbidden();
    }

    public function test_listing_cards_for_missing_workspace_returns_404(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->getJson('/api/v1/workspaces/00000000-0000-0000-0000-000000000000/cards')
            ->assertNotFound();
    }

    public function test_index_only_returns_cards_linked_to_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();

        // A second workspace owned by the same user, NOT linked to the cards below.
        $otherWorkspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $otherWorkspace->members()->attach($user->id, ['role' => 'owner']);

        // The trait seeded one card linked to $workspace. Add one more so the
        // index returns exactly 2 cards and we can verify scoping.
        $extra = Card::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
        $extra->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        // A card belongs to the other workspace; it must NOT show up
        // when listing the first workspace's cards.
        $foreign = Card::factory()->create([
            'workspace_id' => $otherWorkspace->id,
            'user_id' => $user->id,
        ]);
        $foreign->workspaces()->attach($otherWorkspace->id, ['is_shared' => true, 'is_active' => true]);

        $this->actingAsUser($user);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/cards");

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $returnedIds = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($foreign->id, $returnedIds);
    }
}
