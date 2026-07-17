<?php

use App\Exceptions\Renderers\ApiExceptionRenderer;
use App\Http\Middleware\ApiPermissionMiddleware;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureWorkspaceCanManageCategories;
use App\Http\Middleware\EnsureWorkspaceMember;
use App\Http\Middleware\EnsureWorkspaceOwner;
use App\Http\Middleware\SetApiLocale;
use App\Support\Api\ApiExceptionLogger;
use App\Support\Http\RequestId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->api(prepend: [SetApiLocale::class]);
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'api.permission' => ApiPermissionMiddleware::class,
            'verified' => EnsureEmailIsVerified::class,
            'workspace.can_manage_categories' => EnsureWorkspaceCanManageCategories::class,
            'workspace.member' => EnsureWorkspaceMember::class,
            'workspace.owner' => EnsureWorkspaceOwner::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request, \Throwable $throwable): bool => $request->is('api/*'));

        $exceptions->dontReportWhen(function (\Throwable $throwable): bool {
            if (! app()->bound('request')) {
                return false;
            }

            return app(Request::class)->is('api/*');
        });

        $exceptions->context(function (\Throwable $throwable, array $context): array {
            if (! app()->bound('request')) {
                return [];
            }

            $request = app(Request::class);
            $route = $request->route();

            return array_filter([
                'request_id' => RequestId::resolve($request),
                'method' => $request->method(),
                'path' => $request->path(),
                'route_name' => $route?->getName(),
                'user_id' => $request->user()?->getAuthIdentifier(),
                'ip' => $request->ip(),
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
        });

        $exceptions->render(function (\Throwable $throwable, Request $request) {
            $response = app(ApiExceptionRenderer::class)->render($throwable, $request);

            if ($response !== null) {
                app(ApiExceptionLogger::class)->handle($throwable, $request, $response);
            }

            return $response;
        });
    })->create();
