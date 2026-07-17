<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Support\Api\AuthorizationContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

class EnsureWorkspaceOwner
{
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var Workspace|null $workspace */
        $workspace = $request->route('workspace');
        $user = $request->user();
        $workspaceId = $workspace?->getKey();

        if ($user === null) {
            AuthorizationContext::store($request, [
                'middleware' => 'workspace.owner',
                'type' => 'workspace_role',
                'workspace_id' => $workspaceId,
                'resource_type' => 'workspace',
                'resource_id' => $workspaceId,
                'required_role' => 'owner',
                'authorization_reason' => 'unauthenticated',
            ]);

            throw new AuthenticationException;
        }

        if (! $workspace) {
            AuthorizationContext::store($request, [
                'middleware' => 'workspace.owner',
                'type' => 'workspace_role',
                'required_role' => 'owner',
                'authorization_reason' => 'workspace_route_parameter_missing',
            ]);

            throw new AuthorizationException;
        }

        if ($workspace->owner_id !== $user->id) {
            AuthorizationContext::store($request, [
                'middleware' => 'workspace.owner',
                'type' => 'workspace_role',
                'workspace_id' => $workspaceId,
                'resource_type' => 'workspace',
                'resource_id' => $workspaceId,
                'required_role' => 'owner',
                'actual_workspace_role' => $workspace->memberRole($user->id),
                'authorization_reason' => 'workspace_owner_required',
            ]);

            throw new AuthorizationException;
        }

        return $next($request);
    }
}
