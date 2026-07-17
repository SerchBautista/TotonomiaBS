<?php

namespace Tests\Feature;

use App\Exceptions\DomainConflictException;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

class ApiExceptionHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        if (! Route::has('tests.api-exceptions.unauthenticated')) {
            Route::middleware('api')->prefix('api/v1/_test/exceptions')->group(function (): void {
                Route::get('unauthenticated', fn () => response()->json(['ok' => true]))
                    ->middleware('auth:api')
                    ->name('tests.api-exceptions.unauthenticated');

                Route::get('forbidden', function () {
                    throw new AuthorizationException;
                })->name('tests.api-exceptions.forbidden');

                Route::get('model/{user}', fn (User $user) => response()->json(['id' => $user->id]))
                    ->name('tests.api-exceptions.model');

                Route::post('validation', function (Request $request) {
                    $request->validate([
                        'email' => ['required', 'email'],
                    ]);

                    return response()->json(['ok' => true]);
                })->name('tests.api-exceptions.validation');

                Route::post('validation-nested', function (Request $request) {
                    $request->validate([
                        'users.0.email' => ['required', 'email'],
                    ]);

                    return response()->json(['ok' => true]);
                })->name('tests.api-exceptions.validation-nested');

                Route::get('conflict', function () {
                    throw new DomainConflictException(
                        'resource_conflict',
                        'Resource state conflicts with this request.',
                    );
                })->name('tests.api-exceptions.conflict');

                Route::get('server-error', function () {
                    throw new RuntimeException('Sensitive internal details.');
                })->name('tests.api-exceptions.server-error');

                Route::get('http-server-error', function () {
                    throw new HttpException(500, 'Raw http exception details.');
                })->name('tests.api-exceptions.http-server-error');

                Route::get('too-many-requests', function () {
                    throw new TooManyRequestsHttpException;
                })->name('tests.api-exceptions.too-many-requests');

                Route::get('permission-protected', fn () => response()->json(['ok' => true]))
                    ->middleware(['auth:api', 'api.permission:reports.view'])
                    ->name('tests.api-exceptions.permission-protected');

                Route::get('workspaces/{workspace}/members-only', fn (Workspace $workspace) => response()->json(['id' => $workspace->id]))
                    ->middleware(['auth:api', 'workspace.member'])
                    ->name('tests.api-exceptions.workspace-member');

                Route::get('workspaces/{workspace}/owner-only', fn (Workspace $workspace) => response()->json(['id' => $workspace->id]))
                    ->middleware(['auth:api', 'workspace.owner'])
                    ->name('tests.api-exceptions.workspace-owner');
            });
        }
    }

    public function test_authentication_exception_returns_standard_401_response(): void
    {
        $this->withHeader('X-Request-Id', 'req-auth-401')
            ->getJson('/api/v1/_test/exceptions/unauthenticated')
            ->assertUnauthorized()
            ->assertExactJson([
                'status' => 401,
                'code' => 'unauthenticated',
                'message' => 'Authentication is required to access this resource.',
                'request_id' => 'req-auth-401',
            ]);
    }

    public function test_authorization_exception_returns_standard_403_response(): void
    {
        $this->withHeader('X-Request-Id', 'req-authz-403')
            ->getJson('/api/v1/_test/exceptions/forbidden')
            ->assertForbidden()
            ->assertExactJson([
                'status' => 403,
                'code' => 'forbidden',
                'message' => 'You do not have permission to access this resource.',
                'request_id' => 'req-authz-403',
            ]);
    }

    public function test_authorization_exception_logs_enriched_context_once(): void
    {
        Log::spy();

        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->withHeader('X-Request-Id', 'req-authz-log-403')
            ->getJson('/api/v1/_test/exceptions/forbidden')
            ->assertForbidden();

        Log::shouldHaveReceived('log')
            ->once()
            ->withArgs(function (string $level, string $message, array $context) use ($user): bool {
                $this->assertSame('warning', $level);
                $this->assertSame('API exception rendered', $message);
                $this->assertSame('req-authz-log-403', $context['request_id']);
                $this->assertSame(403, $context['status']);
                $this->assertSame('forbidden', $context['code']);
                $this->assertSame(AccessDeniedHttpException::class, $context['exception_class']);
                $this->assertSame('This action is unauthorized.', $context['exception_message']);
                $this->assertSame(AuthorizationException::class, $context['previous_exception_class']);
                $this->assertSame('GET', $context['method']);
                $this->assertSame('api/v1/_test/exceptions/forbidden', $context['path']);
                $this->assertSame('tests.api-exceptions.forbidden', $context['route_name']);
                $this->assertSame('Closure', $context['controller_action']);
                $this->assertSame($user->id, $context['user_id']);
                $this->assertSame('127.0.0.1', $context['ip']);
                $this->assertArrayNotHasKey('exception', $context);

                return true;
            });
    }

    public function test_permission_middleware_logs_failed_permission_context(): void
    {
        Log::spy();

        Permission::firstOrCreate(['name' => 'reports.view', 'guard_name' => 'api']);

        $user = User::factory()->create();

        $this->actingAs($user, 'api')
            ->withHeader('X-Request-Id', 'req-permission-log-403')
            ->getJson('/api/v1/_test/exceptions/permission-protected')
            ->assertForbidden();

        Log::shouldHaveReceived('log')
            ->once()
            ->withArgs(function (string $level, string $message, array $context) use ($user): bool {
                $this->assertSame('warning', $level);
                $this->assertSame('API exception rendered', $message);
                $this->assertSame('req-permission-log-403', $context['request_id']);
                $this->assertSame('tests.api-exceptions.permission-protected', $context['route_name']);
                $this->assertSame('Closure', $context['controller_action']);
                $this->assertSame($user->id, $context['user_id']);
                $this->assertSame('api.permission', $context['authorization_middleware']);
                $this->assertSame('missing_permission', $context['authorization_reason']);
                $this->assertSame('reports.view', $context['required_permission']);
                $this->assertSame([
                    'middleware' => 'api.permission',
                    'type' => 'permission',
                    'guard' => 'api',
                    'required_permission' => 'reports.view',
                    'authorization_reason' => 'missing_permission',
                ], $context['authorization_context']);
                $this->assertArrayNotHasKey('exception', $context);

                return true;
            });
    }

    public function test_workspace_member_middleware_logs_workspace_membership_context(): void
    {
        Log::spy();

        $workspace = Workspace::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider, 'api')
            ->withHeader('X-Request-Id', 'req-workspace-member-log-403')
            ->getJson("/api/v1/_test/exceptions/workspaces/{$workspace->id}/members-only")
            ->assertForbidden();

        Log::shouldHaveReceived('log')
            ->once()
            ->withArgs(function (string $level, string $message, array $context) use ($workspace, $outsider): bool {
                $this->assertSame('warning', $level);
                $this->assertSame('API exception rendered', $message);
                $this->assertSame('req-workspace-member-log-403', $context['request_id']);
                $this->assertSame($outsider->id, $context['user_id']);
                $this->assertSame('tests.api-exceptions.workspace-member', $context['route_name']);
                $this->assertSame('Closure', $context['controller_action']);
                $this->assertSame('workspace.member', $context['authorization_middleware']);
                $this->assertSame('user_is_not_workspace_member', $context['authorization_reason']);
                $this->assertSame('member', $context['required_role']);
                $this->assertSame('workspace', $context['resource_type']);
                $this->assertSame($workspace->id, $context['resource_id']);
                $this->assertSame($workspace->id, $context['workspace_id']);
                $this->assertSame([
                    'middleware' => 'workspace.member',
                    'type' => 'workspace_membership',
                    'workspace_id' => $workspace->id,
                    'resource_type' => 'workspace',
                    'resource_id' => $workspace->id,
                    'required_role' => 'member',
                    'authorization_reason' => 'user_is_not_workspace_member',
                ], $context['authorization_context']);
                $this->assertArrayNotHasKey('exception', $context);

                return true;
            });
    }

    public function test_workspace_owner_middleware_logs_workspace_role_context(): void
    {
        Log::spy();

        $workspace = Workspace::factory()->create();
        $viewer = User::factory()->create();
        $workspace->members()->attach($viewer->id, ['role' => 'viewer']);

        $this->actingAs($viewer, 'api')
            ->withHeader('X-Request-Id', 'req-workspace-owner-log-403')
            ->getJson("/api/v1/_test/exceptions/workspaces/{$workspace->id}/owner-only")
            ->assertForbidden();

        Log::shouldHaveReceived('log')
            ->once()
            ->withArgs(function (string $level, string $message, array $context) use ($workspace, $viewer): bool {
                $this->assertSame('warning', $level);
                $this->assertSame('API exception rendered', $message);
                $this->assertSame('req-workspace-owner-log-403', $context['request_id']);
                $this->assertSame($viewer->id, $context['user_id']);
                $this->assertSame('tests.api-exceptions.workspace-owner', $context['route_name']);
                $this->assertSame('Closure', $context['controller_action']);
                $this->assertSame('workspace.owner', $context['authorization_middleware']);
                $this->assertSame('workspace_owner_required', $context['authorization_reason']);
                $this->assertSame('owner', $context['required_role']);
                $this->assertSame('workspace', $context['resource_type']);
                $this->assertSame($workspace->id, $context['resource_id']);
                $this->assertSame($workspace->id, $context['workspace_id']);
                $this->assertSame([
                    'middleware' => 'workspace.owner',
                    'type' => 'workspace_role',
                    'workspace_id' => $workspace->id,
                    'resource_type' => 'workspace',
                    'resource_id' => $workspace->id,
                    'required_role' => 'owner',
                    'actual_workspace_role' => 'viewer',
                    'authorization_reason' => 'workspace_owner_required',
                ], $context['authorization_context']);
                $this->assertArrayNotHasKey('exception', $context);

                return true;
            });
    }

    public function test_model_not_found_exception_returns_standard_404_response(): void
    {
        $this->withHeader('X-Request-Id', 'req-model-404')
            ->getJson('/api/v1/_test/exceptions/model/999999')
            ->assertNotFound()
            ->assertExactJson([
                'status' => 404,
                'code' => 'not_found',
                'message' => 'The requested resource was not found.',
                'request_id' => 'req-model-404',
            ]);
    }

    public function test_missing_api_route_returns_standard_404_response(): void
    {
        $this->withHeader('X-Request-Id', 'req-route-404')
            ->getJson('/api/v1/_test/exceptions/missing-route')
            ->assertNotFound()
            ->assertExactJson([
                'status' => 404,
                'code' => 'not_found',
                'message' => 'The requested resource was not found.',
                'request_id' => 'req-route-404',
            ]);
    }

    public function test_validation_exception_returns_standard_422_response_with_field_errors(): void
    {
        $this->withHeader('X-Request-Id', 'req-validation-422')
            ->postJson('/api/v1/_test/exceptions/validation', [])
            ->assertUnprocessable()
            ->assertExactJson([
                'status' => 422,
                'code' => 'validation_error',
                'message' => 'The given data was invalid.',
                'request_id' => 'req-validation-422',
                'fieldErrors' => [
                    'email' => ['The email field is required.'],
                ],
            ]);
    }

    public function test_validation_exception_keeps_dot_notation_field_names_for_nested_inputs(): void
    {
        $this->withHeader('X-Request-Id', 'req-validation-nested-422')
            ->postJson('/api/v1/_test/exceptions/validation-nested', [
                'users' => [
                    ['email' => 'invalid-email'],
                ],
            ])
            ->assertUnprocessable()
            ->assertExactJson([
                'status' => 422,
                'code' => 'validation_error',
                'message' => 'The given data was invalid.',
                'request_id' => 'req-validation-nested-422',
                'fieldErrors' => [
                    'users.0.email' => ['The users.0.email field must be a valid email address.'],
                ],
            ]);
    }

    public function test_domain_conflict_exception_returns_standard_409_response(): void
    {
        $this->withHeader('X-Request-Id', 'req-conflict-409')
            ->getJson('/api/v1/_test/exceptions/conflict')
            ->assertConflict()
            ->assertExactJson([
                'status' => 409,
                'code' => 'resource_conflict',
                'message' => 'Resource state conflicts with this request.',
                'request_id' => 'req-conflict-409',
            ]);
    }

    public function test_unhandled_exception_returns_safe_standard_500_response(): void
    {
        $response = $this->withHeader('X-Request-Id', 'req-server-500')
            ->getJson('/api/v1/_test/exceptions/server-error');

        $response
            ->assertStatus(500)
            ->assertExactJson([
                'status' => 500,
                'code' => 'server_error',
                'message' => 'An unexpected error occurred.',
                'request_id' => 'req-server-500',
            ]);

        $this->assertStringNotContainsString('Sensitive internal details.', $response->getContent());
    }

    public function test_unhandled_exception_logs_enriched_context_once(): void
    {
        Log::spy();

        $this->withHeader('X-Request-Id', 'req-server-log-500')
            ->getJson('/api/v1/_test/exceptions/server-error')
            ->assertStatus(500);

        Log::shouldHaveReceived('log')
            ->once()
            ->withArgs(function (string $level, string $message, array $context): bool {
                $this->assertSame('error', $level);
                $this->assertSame('API exception rendered', $message);
                $this->assertSame('req-server-log-500', $context['request_id']);
                $this->assertSame(500, $context['status']);
                $this->assertSame('server_error', $context['code']);
                $this->assertSame(RuntimeException::class, $context['exception_class']);
                $this->assertSame('Sensitive internal details.', $context['exception_message']);
                $this->assertSame('GET', $context['method']);
                $this->assertSame('api/v1/_test/exceptions/server-error', $context['path']);
                $this->assertSame('tests.api-exceptions.server-error', $context['route_name']);
                $this->assertSame('Closure', $context['controller_action']);
                $this->assertSame('127.0.0.1', $context['ip']);
                $this->assertArrayNotHasKey('exception', $context);

                return true;
            });
    }

    public function test_http_500_exception_returns_standard_server_error_contract(): void
    {
        $response = $this->withHeader('X-Request-Id', 'req-http-500')
            ->getJson('/api/v1/_test/exceptions/http-server-error');

        $response
            ->assertStatus(500)
            ->assertExactJson([
                'status' => 500,
                'code' => 'server_error',
                'message' => 'An unexpected error occurred.',
                'request_id' => 'req-http-500',
            ]);

        $this->assertStringNotContainsString('Raw http exception details.', $response->getContent());
    }

    public function test_http_429_exception_returns_standard_rate_limit_contract(): void
    {
        $this->withHeader('X-Request-Id', 'req-http-429')
            ->getJson('/api/v1/_test/exceptions/too-many-requests')
            ->assertStatus(429)
            ->assertExactJson([
                'status' => 429,
                'code' => 'too_many_requests',
                'message' => 'Too many requests. Please try again later.',
                'request_id' => 'req-http-429',
            ]);
    }

    public function test_invalid_request_id_header_is_replaced_with_a_safe_generated_identifier(): void
    {
        $response = $this->withHeader('X-Request-Id', 'invalid request id')
            ->getJson('/api/v1/_test/exceptions/server-error');

        $response
            ->assertStatus(500)
            ->assertJsonPath('status', 500)
            ->assertJsonPath('code', 'server_error')
            ->assertJsonPath('message', 'An unexpected error occurred.');

        $requestId = $response->json('request_id');

        $this->assertIsString($requestId);
        $this->assertNotSame('invalid request id', $requestId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $requestId,
        );
    }
}
