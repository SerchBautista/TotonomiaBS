<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainValidationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\FixedExpense\StoreFixedExpenseRequest;
use App\Http\Requests\FixedExpense\UpdateFixedExpenseRequest;
use App\Http\Resources\FixedExpenseResource;
use App\Models\FixedExpense;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class FixedExpenseController extends Controller
{
    public function index(Workspace $workspace): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [FixedExpense::class, $workspace]);

        $fixedExpenses = $workspace->fixedExpenses()
            ->with('category', 'paymentInstrument')
            ->get();

        return FixedExpenseResource::collection($fixedExpenses);
    }

    public function store(StoreFixedExpenseRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('create', [FixedExpense::class, $workspace]);

        $data = array_merge($request->validated(), ['user_id' => $request->user()->id]);

        $fixedExpense = $workspace->fixedExpenses()->create($data);

        return (new FixedExpenseResource($fixedExpense->load('category', 'paymentInstrument')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateFixedExpenseRequest $request, Workspace $workspace, FixedExpense $fixedExpense): JsonResponse
    {
        $this->authorize('update', $fixedExpense);

        $fixedExpense->update($request->validated());

        return (new FixedExpenseResource($fixedExpense->load('category', 'paymentInstrument')))->response();
    }

    #[OA\Delete(
        path: '/workspaces/{workspace}/fixed-expenses/{fixedExpense}',
        tags: ['Fixed Expenses'],
        summary: 'Delete a fixed expense',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'fixedExpense', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Fixed expense deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 404, description: 'Fixed expense not found', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(
                response: 422,
                description: 'Business rule violation: fixed expense has paid occurrences',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 422,
                            'code' => 'fixed_expense_has_paid_occurrences',
                            'message' => 'A fixed expense with recorded payments cannot be deleted.',
                        ]),
                    ]
                )
            ),
        ]
    )]
    public function destroy(Workspace $workspace, FixedExpense $fixedExpense): JsonResponse
    {
        $this->authorize('delete', $fixedExpense);

        if ($fixedExpense->occurrences()->where('status', 'paid')->exists()) {
            throw new DomainValidationException(
                'fixed_expense_has_paid_occurrences',
                __('api.fixed_expenses.cannot_delete_with_paid_occurrences'),
            );
        }

        $fixedExpense->delete();

        return response()->json(null, 204);
    }
}
