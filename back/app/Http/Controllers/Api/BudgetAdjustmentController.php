<?php

namespace App\Http\Controllers\Api;

use App\Contracts\CalculateEffectiveBudgetActionInterface;
use App\Contracts\SuggestCategoriesForAdjustmentActionInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\BudgetAdjustment\AvailableRequest;
use App\Http\Requests\BudgetAdjustment\IndexRequest;
use App\Http\Requests\BudgetAdjustment\StoreRequest;
use App\Http\Resources\BudgetAdjustmentResource;
use App\Models\BudgetAdjustment;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class BudgetAdjustmentController extends Controller
{
    public function __construct(
        private readonly CalculateEffectiveBudgetActionInterface $effectiveBudgetAction,
        private readonly SuggestCategoriesForAdjustmentActionInterface $suggestAction,
    ) {}

    #[OA\Get(
        path: '/workspaces/{workspace}/budget-adjustments',
        tags: ['Budget Adjustments'],
        summary: 'List budget adjustments for a workspace and month',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'month', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: '2026-03')),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Budget adjustments loaded'),
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
                            'request_id' => 'req-budget-adjustment-index-422',
                            'fieldErrors' => [
                                'category_id' => ['The selected category id is invalid for the current workspace.'],
                            ],
                        ]),
                    ]
                )
            ),
        ]
    )]
    public function index(IndexRequest $request, Workspace $workspace): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [BudgetAdjustment::class, $workspace]);

        $validated = $request->validated();

        $month = isset($validated['month'])
            ? Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth()
            : Carbon::now()->startOfMonth();

        $categoryId = $validated['category_id'] ?? null;

        $adjustments = BudgetAdjustment::query()
            ->where('workspace_id', $workspace->id)
            ->whereDate('month', $month->toDateString())
            ->when($categoryId, fn ($q) => $q->where(function ($q) use ($categoryId) {
                $q->where('from_category_id', $categoryId)
                    ->orWhere('to_category_id', $categoryId);
            }))
            ->with(['fromCategory', 'toCategory'])
            ->orderByDesc('created_at')
            ->get();

        return BudgetAdjustmentResource::collection($adjustments);
    }

    #[OA\Post(
        path: '/workspaces/{workspace}/budget-adjustments',
        tags: ['Budget Adjustments'],
        summary: 'Create a budget adjustment',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['from_category_id', 'to_category_id', 'amount', 'month'],
                properties: [
                    new OA\Property(property: 'from_category_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'to_category_id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'amount', type: 'number', format: 'float', example: 100),
                    new OA\Property(property: 'month', type: 'string', example: '2026-03'),
                    new OA\Property(property: 'reason', type: 'string', nullable: true, example: 'Moving funds'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Budget adjustment created'),
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
                            'request_id' => 'req-budget-adjustment-forbidden-403',
                        ]),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation or business rule error',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(
                            allOf: [
                                new OA\Schema(ref: '#/components/schemas/ApiError'),
                                new OA\Schema(example: [
                                    'status' => 422,
                                    'code' => 'validation_error',
                                    'message' => 'The given data was invalid.',
                                    'request_id' => 'req-budget-adjustment-shared-workspace-422',
                                    'fieldErrors' => [
                                        'from_category_id' => ['The selected from category id is invalid for the current workspace.'],
                                        'to_category_id' => ['The selected to category id is invalid for the current workspace.'],
                                    ],
                                ]),
                            ]
                        ),
                        new OA\Schema(
                            allOf: [
                                new OA\Schema(ref: '#/components/schemas/ApiError'),
                                new OA\Schema(example: [
                                    'status' => 422,
                                    'code' => 'budget_adjustment_insufficient_funds',
                                    'message' => 'Insufficient funds in the selected category.',
                                    'request_id' => 'req-budget-adjustment-insufficient-funds-422',
                                    'meta' => [
                                        'suggested_categories' => [
                                            [
                                                'category_id' => '72b6f5ad-03a5-4d32-9779-03a2eb7396c1',
                                                'category_name' => 'Emergency fund',
                                                'available' => '250.00',
                                            ],
                                        ],
                                    ],
                                ]),
                            ]
                        ),
                    ]
                )
            ),
        ]
    )]
    public function store(StoreRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('create', [BudgetAdjustment::class, $workspace]);

        $validated = $request->validated();

        $adjustment = BudgetAdjustment::create([
            'workspace_id' => $workspace->id,
            'month' => Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth(),
            'from_category_id' => $validated['from_category_id'],
            'to_category_id' => $validated['to_category_id'],
            'amount' => $validated['amount'],
            'reason' => $validated['reason'] ?? null,
            'user_id' => $request->user()->id,
        ]);

        $adjustment->load(['fromCategory', 'toCategory']);

        return (new BudgetAdjustmentResource($adjustment))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Delete(
        path: '/workspaces/{workspace}/budget-adjustments/{adjustment}',
        tags: ['Budget Adjustments'],
        summary: 'Delete a budget adjustment',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'adjustment', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Budget adjustment deleted'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
        ]
    )]
    public function destroy(Workspace $workspace, BudgetAdjustment $adjustment): JsonResponse
    {
        $this->authorize('delete', $adjustment);

        $adjustment->delete();

        return response()->json(null, 204);
    }

    #[OA\Get(
        path: '/workspaces/{workspace}/budget-adjustments/available',
        tags: ['Budget Adjustments'],
        summary: 'List suggested categories available for budget adjustment',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'month', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: '2026-03')),
            new OA\Parameter(name: 'exclude_category_id', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Available categories loaded'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
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
                            'request_id' => 'req-budget-adjustment-available-422',
                            'fieldErrors' => [
                                'exclude_category_id' => ['The selected exclude category id is invalid for the current workspace.'],
                            ],
                        ]),
                    ]
                )
            ),
        ]
    )]
    public function available(AvailableRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('viewAny', [BudgetAdjustment::class, $workspace]);

        $validated = $request->validated();

        $month = isset($validated['month'])
            ? Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth()
            : Carbon::now()->startOfMonth();

        $excludeCategoryId = (string) ($validated['exclude_category_id'] ?? '');

        $suggestions = $this->suggestAction->execute($workspace, $excludeCategoryId, $month);

        return response()->json([
            'data' => $suggestions,
        ]);
    }
}
