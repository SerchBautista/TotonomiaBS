<?php

namespace Tests;

use App\Models\Card;
use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Passport\Passport;

trait CreatesWorkspace
{
    /**
     * Create a user with a workspace and return both.
     * The user is added as admin member of the workspace.
     * The category is owned by the user and enabled in the workspace.
     *
     * @return array{user: User, workspace: Workspace, category: Category, card: Card}
     */
    protected function createUserWithWorkspace(array $workspaceOverrides = []): array
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $workspace = Workspace::factory()->create(
            array_merge(['owner_id' => $user->id], $workspaceOverrides)
        );

        $workspace->members()->attach($user->id, ['role' => 'owner']);

        $category = Category::factory()->forUser($user)->create();
        $category->workspaces()->attach($workspace->id);

        $card = Card::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
        $card->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        return compact('user', 'workspace', 'category', 'card');
    }

    /**
     * Authenticate a user via Passport for testing.
     */
    protected function actingAsUser(User $user): static
    {
        Passport::actingAs($user, ['*'], 'api');

        return $this;
    }
}
