<?php

namespace App\Http\Controllers\Api;

use App\Actions\BulkUpdateCategorySharingAction;
use App\Actions\CreateWorkspaceCategoryAction;
use App\Actions\DeleteCategoryAction;
use App\Actions\EnrichCategoryLinkedWorkspacesAction;
use App\Actions\GetValidWorkspaceCategoriesAction;
use App\Actions\ListMyCategoriesAction;
use App\Actions\ListWorkspaceCategorySharingAction;
use App\Actions\SyncCategoryWorkspacesAction;
use App\Actions\UpdateCategoryActivationAction;
use App\Actions\UpdateCategorySharingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Category\AssignCategoryRequest;
use App\Http\Requests\Category\BulkUpdateCategorySharingRequest;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\SyncCategoryWorkspacesRequest;
use App\Http\Requests\Category\UpdateCategoryActivationRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Requests\Category\UpdateCategorySharingRequest;
use App\Http\Resources\BulkOperationResultResource;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    public function index(Workspace $workspace, ListWorkspaceCategorySharingAction $listWorkspaceCategorySharing): AnonymousResourceCollection
    {
        $categories = $listWorkspaceCategorySharing->execute($workspace);

        return CategoryResource::collection($categories);
    }

    public function store(
        StoreCategoryRequest $request,
        Workspace $workspace,
        CreateWorkspaceCategoryAction $createWorkspaceCategory,
    ): JsonResponse {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $category = $createWorkspaceCategory->execute($user, $workspace, $request->validated());

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, Workspace $workspace, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category->update($request->validated());

        return (new CategoryResource($category))->response();
    }

    public function destroy(Workspace $workspace, Category $category, DeleteCategoryAction $deleteCategoryAction): JsonResponse
    {
        $this->authorize('delete', $category);

        $deleteCategoryAction->execute($category);

        return response()->json(null, 204);
    }

    public function setDefault(Workspace $workspace, Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category->setAsDefault();

        return (new CategoryResource($category))->response();
    }

    public function assign(AssignCategoryRequest $request, Workspace $workspace, Category $category, UpdateCategorySharingAction $updateCategorySharing): JsonResponse
    {
        $this->authorize('assign', [Category::class, $workspace]);

        $updateCategorySharing->execute($workspace, $category, true);

        return response()->json(null, 204);
    }

    public function unassign(AssignCategoryRequest $request, Workspace $workspace, Category $category, UpdateCategorySharingAction $updateCategorySharing): JsonResponse
    {
        $this->authorize('assign', [Category::class, $workspace]);

        $updateCategorySharing->execute($workspace, $category, false);

        return response()->json(null, 204);
    }

    #[OA\Get(
        path: '/workspaces/{workspace}/categories/sharing',
        tags: ['Categories'],
        summary: 'List workspace category sharing management flags',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Workspace category sharing list loaded',
                content: new OA\JsonContent(properties: [
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'is_linked', type: 'boolean'),
                            new OA\Property(property: 'is_active_in_workspace', type: 'boolean'),
                            new OA\Property(property: 'is_in_use_in_workspace', type: 'boolean'),
                            new OA\Property(property: 'is_valid_for_transactions', type: 'boolean'),
                            new OA\Property(property: 'state', type: 'string', enum: ['not_linked', 'linked', 'read_only_linked']),
                        ])
                    ),
                ])
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function sharing(Workspace $workspace, ListWorkspaceCategorySharingAction $listWorkspaceCategorySharing): AnonymousResourceCollection
    {
        return $this->index($workspace, $listWorkspaceCategorySharing);
    }

    #[OA\Patch(
        path: '/workspaces/{workspace}/categories/{category}/link',
        tags: ['Categories'],
        summary: 'Share or unshare a category in workspace',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['is_linked'], properties: [
                new OA\Property(property: 'is_linked', type: 'boolean'),
            ])
        ),
        parameters: [
            new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Category sharing updated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updateSharing(
        UpdateCategorySharingRequest $request,
        Workspace $workspace,
        Category $category,
        UpdateCategorySharingAction $updateCategorySharing,
    ): JsonResponse {
        $updateCategorySharing->execute($workspace, $category, (bool) $request->validated('is_linked'));

        return response()->json(null, 204);
    }

    #[OA\Patch(
        path: '/workspaces/{workspace}/categories/{category}/activation',
        tags: ['Categories'],
        summary: 'Activate or deactivate a shared category in workspace',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['is_active'], properties: [
                new OA\Property(property: 'is_active', type: 'boolean'),
            ])
        ),
        parameters: [
            new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Category activation updated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 409, description: 'Business conflict: category_not_shared_in_workspace'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updateActivation(
        UpdateCategoryActivationRequest $request,
        Workspace $workspace,
        Category $category,
        UpdateCategoryActivationAction $updateCategoryActivation,
    ): JsonResponse {
        $updateCategoryActivation->execute($workspace, $category, (bool) $request->validated('is_active'));

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: '/workspaces/{workspace}/categories/link-bulk',
        tags: ['Categories'],
        summary: 'Bulk share or unshare categories in workspace',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['operation'], properties: [
                new OA\Property(property: 'operation', type: 'string', enum: ['link_all', 'unlink_all']),
            ])
        ),
        parameters: [
            new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Bulk operation processed',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'operation', type: 'string', enum: ['link_all', 'unlink_all']),
                    new OA\Property(property: 'total', type: 'integer', example: 10),
                    new OA\Property(property: 'processed', type: 'integer', example: 8),
                    new OA\Property(property: 'blocked', type: 'integer', example: 2),
                    new OA\Property(property: 'processed_category_ids', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
                    new OA\Property(property: 'blocked_category_ids', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
                ])
            ),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function bulkUpdateSharing(
        BulkUpdateCategorySharingRequest $request,
        Workspace $workspace,
        BulkUpdateCategorySharingAction $bulkUpdateCategorySharing,
    ): BulkOperationResultResource {
        $result = $bulkUpdateCategorySharing->execute($workspace, $request->validated('operation'));

        return BulkOperationResultResource::forCategorySharing($result);
    }

    #[OA\Get(
        path: '/workspaces/{workspace}/categories/valid',
        tags: ['Categories'],
        summary: 'List valid categories for transactional flows in a workspace',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Valid categories loaded'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function valid(
        Workspace $workspace,
        GetValidWorkspaceCategoriesAction $getValidWorkspaceCategories,
    ): AnonymousResourceCollection {
        $this->authorize('viewAny', [Category::class, $workspace]);

        $categories = $getValidWorkspaceCategories
            ->execute($workspace)
            ->get();

        return CategoryResource::collection($categories);
    }

    #[OA\Get(
        path: '/user/categories',
        tags: ['Categories'],
        summary: 'List user categories with current shared workspaces',
        description: 'Returns categories owned by the authenticated user. The `linked_workspaces` summary only includes current shared workspace pivots (`is_shared=true`); historical read-only pivots (`is_shared=false`) are excluded.',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Categories loaded'),
        ]
    )]
    public function myCategories(ListMyCategoriesAction $listMyCategories): AnonymousResourceCollection
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return CategoryResource::collection($listMyCategories->execute($user));
    }

    #[OA\Post(
        path: '/user/categories',
        tags: ['Categories'],
        summary: 'Create a category owned by the current user',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['name'], properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 100),
                new OA\Property(property: 'icon', type: 'string', nullable: true, maxLength: 50),
                new OA\Property(property: 'color', type: 'string', nullable: true, example: '#FF5733'),
                new OA\Property(property: 'workspace_ids', type: 'array', nullable: true, items: new OA\Items(type: 'string', format: 'uuid')),
            ])
        ),
        responses: [
            new OA\Response(response: 201, description: 'Category created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden — email not verified'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function storeMine(
        StoreCategoryRequest $request,
        SyncCategoryWorkspacesAction $syncCategoryWorkspaces,
        EnrichCategoryLinkedWorkspacesAction $enrichCategoryLinkedWorkspaces,
    ): JsonResponse {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $validated = $request->validated();
        $workspaceIds = $validated['workspace_ids'] ?? null;
        unset($validated['workspace_ids']);

        $category = $user->categories()->create($validated);

        if ($workspaceIds === null) {
            $ownedWorkspaceIds = $user->ownedWorkspaces()->pluck('id');

            if ($ownedWorkspaceIds->count() === 1) {
                $workspaceIds = [$ownedWorkspaceIds->first()];
            }
        }

        if ($workspaceIds !== null && $workspaceIds !== []) {
            $syncCategoryWorkspaces->execute($category, $user, $workspaceIds);
            $enrichCategoryLinkedWorkspaces->execute($category, forceReload: true);
        }

        return (new CategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Put(
        path: '/user/categories/{category}',
        tags: ['Categories'],
        summary: 'Update a category owned by the current user',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 100),
                new OA\Property(property: 'icon', type: 'string', nullable: true, maxLength: 50),
                new OA\Property(property: 'color', type: 'string', nullable: true, example: '#FF5733'),
                new OA\Property(property: 'workspace_ids', type: 'array', nullable: true, items: new OA\Items(type: 'string', format: 'uuid')),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Category updated'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updateMine(
        UpdateCategoryRequest $request,
        Category $category,
        SyncCategoryWorkspacesAction $syncCategoryWorkspaces,
        EnrichCategoryLinkedWorkspacesAction $enrichCategoryLinkedWorkspaces,
    ): JsonResponse {
        $this->authorize('update', $category);

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $validated = $request->validated();
        $workspaceIds = $validated['workspace_ids'] ?? null;
        unset($validated['workspace_ids']);

        $category->update($validated);

        if ($workspaceIds !== null) {
            $syncCategoryWorkspaces->execute($category, $user, $workspaceIds);
            $enrichCategoryLinkedWorkspaces->execute($category, forceReload: true);
        }

        return (new CategoryResource($category))->response();
    }

    #[OA\Patch(
        path: '/user/categories/{category}/workspaces',
        tags: ['Categories'],
        summary: 'Synchronize the workspaces linked to a personal category',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['workspace_ids'], properties: [
                new OA\Property(property: 'workspace_ids', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Category workspaces synchronized'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function syncWorkspaces(
        SyncCategoryWorkspacesRequest $request,
        Category $category,
        SyncCategoryWorkspacesAction $syncCategoryWorkspaces,
        EnrichCategoryLinkedWorkspacesAction $enrichCategoryLinkedWorkspaces,
    ): JsonResponse {
        $this->authorize('update', $category);

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $syncCategoryWorkspaces->execute(
            $category,
            $user,
            $request->validated('workspace_ids')
        );

        $enrichCategoryLinkedWorkspaces->execute($category, forceReload: true);

        return (new CategoryResource($category))->response();
    }

    #[OA\Delete(
        path: '/user/categories/{category}',
        tags: ['Categories'],
        summary: 'Delete a category owned by the current user',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Category deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(
                response: 409,
                description: 'Business conflict: category_in_use',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 409,
                            'code' => 'category_in_use',
                            'message' => 'The category is in use and cannot be removed.',
                            'request_id' => 'req-my-category-delete-conflict-409',
                        ]),
                    ]
                )
            ),
        ]
    )]
    public function destroyMine(Category $category, DeleteCategoryAction $deleteCategoryAction): JsonResponse
    {
        $this->authorize('delete', $category);

        $deleteCategoryAction->execute($category);

        return response()->json(null, 204);
    }

    #[OA\Patch(
        path: '/user/categories/{category}/default',
        tags: ['Categories'],
        summary: 'Toggle the default flag on a personal category',
        description: 'Toggles the `is_default` flag on a category owned by the current user. If the category was already the default, it is unmarked (and no category will be default after the call). If it was not, it becomes the sole default for that user and any previous default is unmarked. There is no request body.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Default flag toggled'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function setDefaultMine(Category $category): JsonResponse
    {
        $this->authorize('update', $category);

        $category->setAsDefault();

        return (new CategoryResource($category))->response();
    }
}
