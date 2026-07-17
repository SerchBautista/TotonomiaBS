<?php

namespace App\Http\Controllers\Api;

use App\Actions\SyncPaymentMethodWorkspacesAction;
use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\OtherPaymentMethod\StoreOtherPaymentMethodRequest;
use App\Http\Requests\OtherPaymentMethod\UpdateOtherPaymentMethodRequest;
use App\Http\Resources\OtherPaymentMethodResource;
use App\Models\OtherPaymentMethod;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class OtherPaymentMethodController extends Controller
{
    public function index(Workspace $workspace): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [OtherPaymentMethod::class, $workspace]);

        return OtherPaymentMethodResource::collection($workspace->otherPaymentMethods()->get());
    }

    public function store(StoreOtherPaymentMethodRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('create', [OtherPaymentMethod::class, $workspace]);

        /** @var User $user */
        $user = auth()->user();

        $validated = $request->validated();
        $additionalWorkspaceIds = $validated['workspace_ids'] ?? [];
        unset($validated['workspace_ids']);

        $method = $workspace->otherPaymentMethods()->create([
            ...$validated,
            'user_id' => $user->id,
        ]);

        $workspaceIds = array_values(array_unique(array_merge([$workspace->id], $additionalWorkspaceIds)));

        app(SyncPaymentMethodWorkspacesAction::class)->execute($method, $user, $workspaceIds);

        return (new OtherPaymentMethodResource($method))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateOtherPaymentMethodRequest $request, Workspace $workspace, OtherPaymentMethod $otherPaymentMethod): JsonResponse
    {
        $this->authorize('update', $otherPaymentMethod);

        /** @var User $user */
        $user = auth()->user();

        $validated = $request->validated();
        $workspaceIds = $validated['workspace_ids'] ?? null;
        unset($validated['workspace_ids']);

        $otherPaymentMethod->update($validated);

        if ($workspaceIds !== null) {
            $workspaceIds = array_values(array_unique(array_merge([$workspace->id], $workspaceIds)));

            app(SyncPaymentMethodWorkspacesAction::class)->execute($otherPaymentMethod, $user, $workspaceIds);
        }

        return (new OtherPaymentMethodResource($otherPaymentMethod))->response();
    }

    public function setDefault(Workspace $workspace, OtherPaymentMethod $otherPaymentMethod): JsonResponse
    {
        $this->authorize('update', $otherPaymentMethod);

        $otherPaymentMethod->setAsDefault($workspace->id);

        return (new OtherPaymentMethodResource($otherPaymentMethod))->response();
    }

    #[OA\Delete(
        path: '/workspaces/{workspace}/other-payment-methods/{otherPaymentMethod}',
        tags: ['Other Payment Methods'],
        summary: 'Delete an other payment method',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'otherPaymentMethod', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Other payment method deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 404, description: 'Other payment method not found', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(
                response: 409,
                description: 'Other payment method is in use (has linked expenses or fixed expenses)',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 409,
                            'code' => 'other_payment_method_in_use',
                            'message' => 'The payment method is in use and cannot be removed.',
                        ]),
                    ]
                )
            ),
        ]
    )]
    public function destroy(Workspace $workspace, OtherPaymentMethod $otherPaymentMethod): JsonResponse
    {
        $this->authorize('delete', $otherPaymentMethod);

        if ($otherPaymentMethod->expenses()->exists() || $otherPaymentMethod->fixedExpenses()->exists()) {
            throw new DomainConflictException(
                'other_payment_method_in_use',
                __('api.errors.other_payment_method_in_use'),
            );
        }

        $otherPaymentMethod->delete();

        return response()->json(null, 204);
    }
}
