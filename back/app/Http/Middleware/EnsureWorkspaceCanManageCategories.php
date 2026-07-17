<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Support\Api\AuthorizationContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;

class EnsureWorkspaceCanManageCategories
{
    public function handle(Request $request, Closure $next): mixed
    {
        /** @var Workspace|null $workspace */
        $workspace = $request->route('workspace');
        $user = $request->user();
        $workspaceId = $workspace?->getKey();

        if ($user === null) {
            AuthorizationContext::store($request, [
                'middleware' => 'workspace.can_manage_categories',
                'type' => 'workspace_capability',
                'workspace_id' => $workspaceId,
                'resource_type' => 'workspace',
                'resource_id' => $workspaceId,
                'required_role' => 'owner',
                'required_permission' => 'manage_categories',
                'authorization_reason' => 'unauthenticated',
            ]);

            throw new AuthenticationException;
        }

        if (! $workspace) {
            AuthorizationContext::store($request, [
                'middleware' => 'workspace.can_manage_categories',
                'type' => 'workspace_capability',
                'required_role' => 'owner',
                'required_permission' => 'manage_categories',
                'authorization_reason' => 'workspace_route_parameter_missing',
            ]);

            throw new AuthorizationException;
        }

        if ($workspace->owner_id === $user->id) {
            return $next($request);
        }

        AuthorizationContext::store($request, [
            'middleware' => 'workspace.can_manage_categories',
            'type' => 'workspace_capability',
            'workspace_id' => $workspaceId,
            'resource_type' => 'workspace',
            'resource_id' => $workspaceId,
            'required_role' => 'owner',
            'required_permission' => 'manage_categories',
            'actual_workspace_role' => $workspace->memberRole($user->id),
            'authorization_reason' => 'workspace_owner_required_for_category_management',
        ]);

        throw new AuthorizationException;
    }
}
