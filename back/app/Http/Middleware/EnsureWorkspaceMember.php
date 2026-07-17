<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Support\Api\AuthorizationContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

class EnsureWorkspaceMember
{
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var Workspace|null $workspace */
        $workspace = $request->route('workspace');
        $user = $request->user();
        $workspaceId = $workspace?->getKey();

        if ($user === null) {
            AuthorizationContext::store($request, [
                'middleware' => 'workspace.member',
                'type' => 'workspace_membership',
                'workspace_id' => $workspaceId,
                'resource_type' => 'workspace',
                'resource_id' => $workspaceId,
                'required_role' => 'member',
                'authorization_reason' => 'unauthenticated',
            ]);

            throw new AuthenticationException;
        }

        if (! $workspace) {
            AuthorizationContext::store($request, [
                'middleware' => 'workspace.member',
                'type' => 'workspace_membership',
                'required_role' => 'member',
                'authorization_reason' => 'workspace_route_parameter_missing',
            ]);

            throw new AuthorizationException;
        }

        if (! $workspace->hasMember($user->id)) {
            AuthorizationContext::store($request, [
                'middleware' => 'workspace.member',
                'type' => 'workspace_membership',
                'workspace_id' => $workspaceId,
                'resource_type' => 'workspace',
                'resource_id' => $workspaceId,
                'required_role' => 'member',
                'authorization_reason' => 'user_is_not_workspace_member',
            ]);

            throw new AuthorizationException;
        }

        return $next($request);
    }
}
