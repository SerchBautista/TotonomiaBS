<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\ListUsersAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUserIndexRequest;
use App\Http\Resources\AdminUserDetailResource;
use App\Http\Resources\AdminUserListResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly ListUsersAction $listUsersAction,
    ) {}

    #[OA\Get(
        path: '/admin/users',
        tags: ['Admin'],
        summary: 'List regular users (excludes admins)',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', enum: [10, 25, 50, 100])),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort_by', in: 'query', schema: new OA\Schema(type: 'string', enum: ['name', 'email', 'created_at', 'subscription_ends_at'])),
            new OA\Parameter(name: 'sort_dir', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'plan', in: 'query', schema: new OA\Schema(type: 'string', enum: ['free', 'premium'])),
            new OA\Parameter(name: 'subscription_status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['active', 'inactive'])),
            new OA\Parameter(name: 'email_verified', in: 'query', schema: new OA\Schema(type: 'string', enum: ['verified', 'unverified'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Users loaded'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(AdminUserIndexRequest $request): JsonResponse
    {
        $result = $this->listUsersAction->execute($request->validated());
        $paginator = $result['paginator'];

        return response()->json([
            'message' => __('api.users.loaded'),
            'data' => [
                'items' => AdminUserListResource::collection($paginator->items())->resolve(),
            ],
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'sort_by' => $result['sort_by'],
                'sort_dir' => $result['sort_dir'],
                'search' => $result['search'],
            ],
        ]);
    }

    #[OA\Get(
        path: '/admin/users/{user}',
        tags: ['Admin'],
        summary: 'Get regular user details',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'User loaded'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function show(User $user): JsonResponse
    {
        if ($user->hasRole('admin', 'api')) {
            abort(404);
        }

        $user->load('roles');

        return response()->json([
            'message' => __('api.users.item_loaded'),
            'data' => [
                'item' => new AdminUserDetailResource($user),
            ],
        ]);
    }
}
