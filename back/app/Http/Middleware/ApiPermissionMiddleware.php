<?php

namespace App\Http\Middleware;

use App\Support\Api\AuthorizationContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiPermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null) {
            AuthorizationContext::store($request, [
                'middleware' => 'api.permission',
                'type' => 'permission',
                'guard' => 'api',
                'required_permission' => $permission,
                'authorization_reason' => 'unauthenticated',
            ]);

            throw new AuthenticationException;
        }

        if (! $user->hasPermissionTo($permission, 'api')) {
            AuthorizationContext::store($request, [
                'middleware' => 'api.permission',
                'type' => 'permission',
                'guard' => 'api',
                'required_permission' => $permission,
                'authorization_reason' => 'missing_permission',
            ]);

            throw new AuthorizationException;
        }

        return $next($request);
    }
}
