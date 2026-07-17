<?php

namespace App\Http\Controllers\Api;

use App\Actions\BulkUpdateWorkspacePaymentMethodLinksAction;
use App\Actions\CreateUserPaymentMethodAction;
use App\Actions\CreateWorkspacePaymentMethodAction;
use App\Actions\GetValidWorkspacePaymentMethodsAction;
use App\Actions\ListUserPaymentMethodsAction;
use App\Actions\ListWorkspacePaymentMethodsAction;
use App\Actions\SyncPaymentMethodWorkspacesAction;
use App\Actions\UpdateWorkspacePaymentMethodLinkAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentMethod\BulkUpdateWorkspacePaymentMethodLinksRequest;
use App\Http\Requests\PaymentMethod\StoreUserPaymentMethodRequest;
use App\Http\Requests\PaymentMethod\StoreWorkspacePaymentMethodRequest;
use App\Http\Requests\PaymentMethod\SyncUserPaymentMethodWorkspacesRequest;
use App\Http\Requests\PaymentMethod\UpdateUserPaymentMethodRequest;
use App\Http\Requests\PaymentMethod\UpdateWorkspacePaymentMethodLinkRequest;
use App\Http\Resources\BulkOperationResultResource;
use App\Http\Resources\PaymentMethodSummaryResource;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class PaymentMethodController extends Controller
{
    #[OA\Get(
        path: '/user/payment-methods',
        tags: ['Payment Methods'],
        summary: 'List user payment methods with current shared workspaces',
        description: 'Returns payment methods owned by the authenticated user. The `linked_workspaces` summary only includes current shared workspace pivots (`is_shared=true`); historical read-only pivots (`is_shared=false`) are excluded.',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Payment methods loaded'),
        ]
    )]
    public function index(ListUserPaymentMethodsAction $listUserPaymentMethods): AnonymousResourceCollection
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return PaymentMethodSummaryResource::collection($listUserPaymentMethods->execute($user));
    }

    #[OA\Get(
        path: '/workspaces/{workspace}/payment-methods',
        tags: ['Payment Methods'],
        summary: 'List workspace payment methods management flags',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Workspace payment methods loaded'),
            new OA\Response(
                response: 403,
                description: 'Forbidden',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 403,
                            'code' => 'forbidden',
                            'message' => 'You do not have permission to access this resource.',
                            'request_id' => 'req-workspace-payment-methods-403',
                        ]),
                    ]
                )
            ),
        ]
    )]
    public function workspaceIndex(
        Workspace $workspace,
        ListWorkspacePaymentMethodsAction $listWorkspacePaymentMethods,
    ): AnonymousResourceCollection {
        return PaymentMethodSummaryResource::collection($listWorkspacePaymentMethods->execute($workspace));
    }

    #[OA\Post(
        path: '/workspaces/{workspace}/payment-methods',
        tags: ['Payment Methods'],
        summary: 'Create and link a user payment method from a workspace',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 201, description: 'Payment method created'),
            new OA\Response(
                response: 403,
                description: 'Forbidden',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiError')
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 422,
                            'code' => 'validation_error',
                            'message' => 'The given data was invalid.',
                            'request_id' => 'req-workspace-payment-method-store-422',
                            'fieldErrors' => [
                                'type' => ['The selected type is invalid.'],
                                'last_4_digits' => ['The last 4 digits field must be 4 digits.'],
                            ],
                        ]),
                    ]
                )
            ),
        ]
    )]
    public function store(
        StoreWorkspacePaymentMethodRequest $request,
        Workspace $workspace,
        CreateWorkspacePaymentMethodAction $createWorkspacePaymentMethod,
    ): JsonResponse {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $paymentMethod = $createWorkspacePaymentMethod->execute($user, $workspace, $request->validated());

        return (new PaymentMethodSummaryResource($paymentMethod))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Patch(
        path: '/workspaces/{workspace}/payment-methods/{method}/link',
        tags: ['Payment Methods'],
        summary: 'Link or unlink a payment method in a workspace',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'method', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Payment method link updated'),
            new OA\Response(
                response: 404,
                description: 'Workspace payment method not found',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 404,
                            'code' => 'workspace_payment_method_not_found',
                            'message' => 'Workspace payment method not found.',
                            'request_id' => 'req-workspace-payment-method-link-404',
                        ]),
                    ]
                )
            ),
            new OA\Response(
                response: 403,
                description: 'Forbidden',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiError')
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 422,
                            'code' => 'validation_error',
                            'message' => 'The given data was invalid.',
                            'request_id' => 'req-workspace-payment-method-link-422',
                            'fieldErrors' => [
                                'is_linked' => ['The is linked field must be true or false.'],
                                'method_id' => ['The method id field must be a valid UUID.'],
                            ],
                        ]),
                    ]
                )
            ),
        ]
    )]
    public function updateLink(
        UpdateWorkspacePaymentMethodLinkRequest $request,
        Workspace $workspace,
        string $method,
        UpdateWorkspacePaymentMethodLinkAction $updateWorkspacePaymentMethodLink,
    ): JsonResponse {
        $updateWorkspacePaymentMethodLink->execute($workspace, $method, (bool) $request->validated('is_linked'));

        return response()->json(null, 204);
    }

    #[OA\Post(
        path: '/workspaces/{workspace}/payment-methods/link-bulk',
        tags: ['Payment Methods'],
        summary: 'Bulk link or unlink workspace payment methods',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Bulk operation processed'),
            new OA\Response(
                response: 403,
                description: 'Forbidden',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiError')
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 422,
                            'code' => 'validation_error',
                            'message' => 'The given data was invalid.',
                            'request_id' => 'req-workspace-payment-methods-bulk-422',
                            'fieldErrors' => [
                                'operation' => ['The selected operation is invalid.'],
                            ],
                        ]),
                    ]
                )
            ),
        ]
    )]
    public function linkBulk(
        BulkUpdateWorkspacePaymentMethodLinksRequest $request,
        Workspace $workspace,
        BulkUpdateWorkspacePaymentMethodLinksAction $bulkUpdateWorkspacePaymentMethodLinks,
    ): BulkOperationResultResource {
        $result = $bulkUpdateWorkspacePaymentMethodLinks->execute($workspace, $request->validated('operation'));

        return BulkOperationResultResource::forPaymentMethodLinks($result);
    }

    #[OA\Get(
        path: '/workspaces/{workspace}/payment-methods/valid',
        tags: ['Payment Methods'],
        summary: 'List valid payment methods for transactional flows in a workspace',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Valid payment methods loaded'),
            new OA\Response(
                response: 403,
                description: 'Forbidden',
                content: new OA\JsonContent(ref: '#/components/schemas/ApiError')
            ),
        ]
    )]
    public function valid(
        Workspace $workspace,
        GetValidWorkspacePaymentMethodsAction $getValidWorkspacePaymentMethods,
    ): AnonymousResourceCollection {
        return PaymentMethodSummaryResource::collection($getValidWorkspacePaymentMethods->execute($workspace));
    }

    #[OA\Post(
        path: '/user/payment-methods',
        tags: ['Payment Methods'],
        summary: 'Create a payment method owned by the current user in their default workspace',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['type', 'name'], properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['card', 'other']),
                new OA\Property(property: 'name', type: 'string', maxLength: 100),
                new OA\Property(property: 'card_type', type: 'string', enum: ['credit', 'debit']),
                new OA\Property(property: 'brand', type: 'string', nullable: true, maxLength: 50),
                new OA\Property(property: 'last_4_digits', type: 'string', nullable: true, minLength: 4, maxLength: 4),
                new OA\Property(property: 'description', type: 'string', nullable: true, maxLength: 1000),
                new OA\Property(property: 'workspace_ids', type: 'array', nullable: true, items: new OA\Items(type: 'string', format: 'uuid')),
            ])
        ),
        responses: [
            new OA\Response(response: 201, description: 'Payment method created'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(
                response: 409,
                description: 'Business conflict: user_has_no_default_workspace',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 409,
                            'code' => 'user_has_no_default_workspace',
                            'message' => 'The user does not have a default workspace to create payment methods in.',
                            'request_id' => 'req-user-payment-method-store-409',
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function storeMine(
        StoreUserPaymentMethodRequest $request,
        CreateUserPaymentMethodAction $createUserPaymentMethod,
    ): JsonResponse {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $validated = $request->validated();
        $workspaceIds = $validated['workspace_ids'] ?? null;
        unset($validated['workspace_ids']);

        if ($workspaceIds !== null && $workspaceIds !== []) {
            $defaultWorkspace = Workspace::find($workspaceIds[0]);

            if ($defaultWorkspace === null || $defaultWorkspace->owner_id !== $user->id) {
                throw new AuthorizationException;
            }
        } elseif ($workspaceIds === []) {
            $defaultWorkspace = $createUserPaymentMethod->resolveDefaultWorkspace($user);
        } else {
            $ownedWorkspaceCount = $user->ownedWorkspaces()->count();

            if ($ownedWorkspaceCount === 1) {
                $defaultWorkspace = $user->ownedWorkspaces()->first();
                $workspaceIds = [$defaultWorkspace->id];
            } else {
                $defaultWorkspace = $createUserPaymentMethod->resolveDefaultWorkspace($user);
            }
        }

        $paymentMethod = $createUserPaymentMethod->execute($user, $defaultWorkspace, $validated, $workspaceIds);

        return (new PaymentMethodSummaryResource($paymentMethod))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Delete(
        path: '/user/payment-methods/{paymentMethod}',
        tags: ['Payment Methods'],
        summary: 'Delete a payment method owned by the current user',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'paymentMethod', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Payment method deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(
                response: 404,
                description: 'Payment method not found',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 404,
                            'code' => 'user_payment_method_not_found',
                            'message' => 'The requested personal payment method was not found.',
                            'request_id' => 'req-user-payment-method-destroy-404',
                        ]),
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'Business conflict: payment_method_in_use',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 409,
                            'code' => 'payment_method_in_use',
                            'message' => 'The payment method is in use and cannot be removed.',
                            'request_id' => 'req-user-payment-method-destroy-409',
                        ]),
                    ]
                )
            ),
        ]
    )]
    #[OA\Patch(
        path: '/user/payment-methods/{paymentMethod}/workspaces',
        tags: ['Payment Methods'],
        summary: 'Synchronize the workspaces linked to a personal payment method',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'paymentMethod', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(required: ['workspace_ids'], properties: [
                new OA\Property(property: 'workspace_ids', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Payment method workspaces synchronized'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Payment method not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function syncWorkspaces(
        SyncUserPaymentMethodWorkspacesRequest $request,
        string $paymentMethod,
        CreateUserPaymentMethodAction $createUserPaymentMethod,
    ): JsonResponse {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $method = $createUserPaymentMethod->resolveOwnedPaymentMethod($user, $paymentMethod);

        app(SyncPaymentMethodWorkspacesAction::class)->execute(
            $method,
            $user,
            $request->validated('workspace_ids')
        );

        return (new PaymentMethodSummaryResource($createUserPaymentMethod->summarize($method)))
            ->response();
    }

    public function destroyMine(
        string $paymentMethod,
        CreateUserPaymentMethodAction $createUserPaymentMethod,
    ): JsonResponse {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $createUserPaymentMethod->delete($user, $paymentMethod);

        return response()->json(null, 204);
    }

    #[OA\Put(
        path: '/user/payment-methods/{paymentMethod}',
        tags: ['Payment Methods'],
        summary: 'Update a payment method owned by the current user',
        description: 'Updates the user-owned payment method identified by {paymentMethod}. The route parameter must belong to the authenticated user; otherwise a 404 `user_payment_method_not_found` is returned. The link pivot (is_shared, is_active) is NOT modified by this endpoint. The default_workspace resolution used by POST /user/payment-methods does not apply here because the method is already attached to a workspace.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'paymentMethod', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(properties: [
                new OA\Property(property: 'type', type: 'string', enum: ['card', 'other']),
                new OA\Property(property: 'name', type: 'string', maxLength: 100),
                new OA\Property(property: 'card_type', type: 'string', enum: ['credit', 'debit']),
                new OA\Property(property: 'brand', type: 'string', nullable: true, maxLength: 50),
                new OA\Property(property: 'last_4_digits', type: 'string', nullable: true, minLength: 4, maxLength: 4),
                new OA\Property(property: 'description', type: 'string', nullable: true, maxLength: 1000),
                new OA\Property(property: 'workspace_ids', type: 'array', nullable: true, items: new OA\Items(type: 'string', format: 'uuid')),
            ])
        ),
        responses: [
            new OA\Response(response: 200, description: 'Payment method updated'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(
                response: 403,
                description: 'Forbidden — email not verified',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 403,
                            'code' => 'email_not_verified',
                            'message' => 'Your email address is not verified.',
                            'request_id' => 'req-user-payment-method-update-403',
                        ]),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Payment method not found',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 404,
                            'code' => 'user_payment_method_not_found',
                            'message' => 'The requested personal payment method was not found.',
                            'request_id' => 'req-user-payment-method-update-404',
                        ]),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 422,
                            'code' => 'validation_error',
                            'message' => 'The given data was invalid.',
                            'request_id' => 'req-user-payment-method-update-422',
                            'fieldErrors' => [
                                'name' => ['The name field is required.'],
                                'last_4_digits' => ['The last 4 digits field must be 4 digits.'],
                            ],
                        ]),
                    ]
                )
            ),
            new OA\Response(response: 429, description: 'Too many requests'),
        ]
    )]
    public function updateMine(
        UpdateUserPaymentMethodRequest $request,
        string $paymentMethod,
        CreateUserPaymentMethodAction $createUserPaymentMethod,
    ): JsonResponse {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $validated = $request->validated();
        $workspaceIds = $validated['workspace_ids'] ?? null;
        unset($validated['workspace_ids']);

        $updated = $createUserPaymentMethod->update($user, $paymentMethod, $validated, $workspaceIds);

        return (new PaymentMethodSummaryResource($updated))
            ->response();
    }
}
