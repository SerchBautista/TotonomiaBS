<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\ListAdministratorsAction;
use App\Contracts\CreateAdministratorActionInterface;
use App\Contracts\UpdateAdministratorActionInterface;
use App\Exceptions\DomainValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdministratorIndexRequest;
use App\Http\Requests\Admin\StoreAdministratorRequest;
use App\Http\Requests\Admin\UpdateAdministratorRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdministratorController extends Controller
{
    public function __construct(
        private readonly CreateAdministratorActionInterface $createAdministrator,
        private readonly UpdateAdministratorActionInterface $updateAdministrator,
    ) {}

    #[OA\Get(
        path: '/admin/administrators',
        tags: ['Admin'],
        summary: 'List administrator users',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', enum: [10, 25, 50, 100])),
            new OA\Parameter(name: 'sort_by', in: 'query', schema: new OA\Schema(type: 'string', enum: ['name', 'email', 'created_at'])),
            new OA\Parameter(name: 'sort_dir', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Administrators loaded'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(AdministratorIndexRequest $request, ListAdministratorsAction $listAdministrators): JsonResponse
    {
        $result = $listAdministrators->execute($request->validated());
        $paginator = $result['paginator'];

        return response()->json([
            'message' => __('api.administrators.loaded'),
            'data' => [
                'items' => AdminUserResource::collection($paginator->items())->resolve(),
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
        path: '/admin/administrators/options',
        tags: ['Admin'],
        summary: 'Get roles and permissions for administrator form',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Options loaded'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function options(): JsonResponse
    {
        return response()->json([
            'message' => __('api.administrators.options_loaded'),
            'data' => [
                'roles' => Role::query()
                    ->where('guard_name', 'api')
                    ->orderBy('name')
                    ->pluck('name')
                    ->values(),
                'permissions' => Permission::query()
                    ->where('guard_name', 'api')
                    ->orderBy('name')
                    ->pluck('name')
                    ->values(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/admin/administrators',
        tags: ['Admin'],
        summary: 'Create administrator user',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation', 'roles'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 120),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', minLength: 8),
                    new OA\Property(property: 'password_confirmation', type: 'string', minLength: 8),
                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Administrator created'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function store(StoreAdministratorRequest $request): JsonResponse
    {
        $administrator = $this->createAdministrator->execute($request->validated());

        return response()->json([
            'message' => __('api.administrators.created'),
            'data' => [
                'item' => new AdminUserResource($administrator),
            ],
        ], 201);
    }

    #[OA\Get(
        path: '/admin/administrators/{administrator}',
        tags: ['Admin'],
        summary: 'Get administrator details',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'administrator', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Administrator loaded'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function show(User $administrator): JsonResponse
    {
        abort_unless($administrator->hasRole('admin', 'api'), 404);

        $administrator->load(['roles:id,name', 'permissions:id,name']);

        return response()->json([
            'message' => __('api.administrators.item_loaded'),
            'data' => [
                'item' => new AdminUserResource($administrator),
            ],
        ]);
    }

    #[OA\Put(
        path: '/admin/administrators/{administrator}',
        tags: ['Admin'],
        summary: 'Update administrator user',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'administrator', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'roles'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 120),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', nullable: true, minLength: 8),
                    new OA\Property(property: 'password_confirmation', type: 'string', nullable: true, minLength: 8),
                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Administrator updated'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function update(UpdateAdministratorRequest $request, User $administrator): JsonResponse
    {
        abort_unless($administrator->hasRole('admin', 'api'), 404);

        $administrator = $this->updateAdministrator->execute($administrator, $request->validated());

        return response()->json([
            'message' => __('api.administrators.updated'),
            'data' => [
                'item' => new AdminUserResource($administrator),
            ],
        ]);
    }

    #[OA\Delete(
        path: '/admin/administrators/{administrator}',
        tags: ['Admin'],
        summary: 'Delete administrator user',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'administrator', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Administrator deleted'),
            new OA\Response(response: 422, description: 'Cannot delete own account'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function destroy(Request $request, User $administrator): JsonResponse
    {
        abort_unless($administrator->hasRole('admin', 'api'), 404);

        if ($request->user()?->id === $administrator->id) {
            throw new DomainValidationException(
                'cannot_delete_self',
                __('api.administrators.cannot_delete_self'),
            );
        }

        $administrator->delete();

        return response()->json([
            'message' => __('api.administrators.deleted'),
        ]);
    }
}
