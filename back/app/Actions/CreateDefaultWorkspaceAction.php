<?php

namespace App\Actions;

use App\Contracts\CreateDefaultWorkspaceActionInterface;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateDefaultWorkspaceAction implements CreateDefaultWorkspaceActionInterface
{
    public function execute(User $user): void
    {
        DB::transaction(function () use ($user) {
            $workspace = \App\Models\Workspace::create([
                'owner_id' => $user->id,
                'name' => 'General',
                'type' => 'personal',
                'currency_code' => 'MXN',
            ]);

            $workspace->members()->attach($user->id, [
                'role' => 'owner',
                'can_add_fixed_expenses' => true,
                'can_add_categories' => true,
            ]);

            $category = $user->categories()->create([
                'name' => 'General',
                'icon' => null,
                'color' => '#6366f1',
            ]);

            $category->workspaces()->attach($workspace->id);

            $user->update(['default_workspace_id' => $workspace->id]);
        });
    }
}
