<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class CardUpdateTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_owner_can_update_card(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $response = $this->putJson("/api/v1/workspaces/{$workspace->id}/cards/{$workspace->cards->first()->id}", [
            'name' => 'Renamed Card',
            'card_type' => 'debit',
            'last_4_digits' => '1234',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Renamed Card')
            ->assertJsonPath('data.card_type', 'debit');

        $this->assertDatabaseHas('cards', [
            'id' => $workspace->cards->first()->id,
            'name' => 'Renamed Card',
            'card_type' => 'debit',
        ]);
    }

    public function test_non_owner_editor_member_cannot_update_card(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();

        $editor = User::factory()->create();
        $editor->assignRole('user');
        $workspace->members()->attach($editor->id, ['role' => 'editor']);

        $this->actingAsUser($editor);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/cards/{$workspace->cards->first()->id}", [
            'name' => 'Should Not Update',
        ])->assertForbidden();
    }

    public function test_non_owner_viewer_member_cannot_update_card(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();

        $viewer = User::factory()->create();
        $viewer->assignRole('user');
        $workspace->members()->attach($viewer->id, ['role' => 'viewer']);

        $this->actingAsUser($viewer);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/cards/{$workspace->cards->first()->id}", [
            'name' => 'Should Not Update',
        ])->assertForbidden();
    }

    public function test_non_member_cannot_update_card(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();

        $outsider = User::factory()->create();
        $outsider->assignRole('user');
        $this->actingAsUser($outsider);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/cards/{$workspace->cards->first()->id}", [
            'name' => 'Should Not Update',
        ])->assertForbidden();
    }

    public function test_update_card_with_invalid_card_type_returns_422(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $response = $this->putJson("/api/v1/workspaces/{$workspace->id}/cards/{$workspace->cards->first()->id}", [
            'card_type' => 'crypto',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['fieldErrors' => ['card_type']]);
    }

    public function test_update_card_with_invalid_last_4_digits_returns_422(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $response = $this->putJson("/api/v1/workspaces/{$workspace->id}/cards/{$workspace->cards->first()->id}", [
            'last_4_digits' => 'not-four-digits',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['fieldErrors' => ['last_4_digits']]);
    }

    public function test_update_nonexistent_card_returns_404(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/cards/00000000-0000-0000-0000-000000000000", [
            'name' => 'Missing',
        ])->assertNotFound();
    }
}
