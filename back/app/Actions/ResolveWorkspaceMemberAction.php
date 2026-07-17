<?php

namespace App\Actions;

use App\Exceptions\DomainNotFoundException;
use App\Models\User;
use App\Models\Workspace;

class ResolveWorkspaceMemberAction
{
    public function execute(Workspace $workspace, string $memberId): User
    {
        $member = $workspace->members()
            ->where('users.id', $memberId)
            ->first();

        if ($member instanceof User) {
            return $member;
        }

        throw new DomainNotFoundException(
            'workspace_member_not_found',
            __('api.workspace_members.member_not_found'),
        );
    }
}
