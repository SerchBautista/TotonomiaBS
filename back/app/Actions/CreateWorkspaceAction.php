<?php

namespace App\Actions;

use App\Contracts\CreateWorkspaceActionInterface;
use App\Models\User;
use App\Models\Workspace;

class CreateWorkspaceAction implements CreateWorkspaceActionInterface
{
    /**
     * Create a new workspace and add the owner as an admin member.
     *
     * @param  array{name: string, type: string, currency_code: string}  $data
     */
    public function execute(User $owner, array $data): Workspace
    {
        $workspace = Workspace::create([
            'owner_id' => $owner->id,
            'name' => $data['name'],
            'type' => $data['type'] ?? 'personal',
            'currency_code' => $data['currency_code'] ?? 'USD',
        ]);

        // Attach owner as owner member
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        return $workspace->load('owner', 'members');
    }
}
