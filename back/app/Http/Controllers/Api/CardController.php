<?php

namespace App\Http\Controllers\Api;

use App\Actions\SyncPaymentMethodWorkspacesAction;
use App\Exceptions\DomainConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Card\StoreCardRequest;
use App\Http\Requests\Card\UpdateCardRequest;
use App\Http\Resources\CardResource;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class CardController extends Controller
{
    public function index(Workspace $workspace): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Card::class, $workspace]);

        return CardResource::collection($workspace->cards()->get());
    }

    public function store(StoreCardRequest $request, Workspace $workspace): JsonResponse
    {
        $this->authorize('create', [Card::class, $workspace]);

        /** @var User $user */
        $user = auth()->user();

        $validated = $request->validated();
        $additionalWorkspaceIds = $validated['workspace_ids'] ?? [];
        unset($validated['workspace_ids']);

        $card = $workspace->cards()->create([
            ...$validated,
            'user_id' => $user->id,
        ]);

        $workspaceIds = array_values(array_unique(array_merge([$workspace->id], $additionalWorkspaceIds)));

        app(SyncPaymentMethodWorkspacesAction::class)->execute($card, $user, $workspaceIds);

        return (new CardResource($card))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateCardRequest $request, Workspace $workspace, Card $card): JsonResponse
    {
        $this->authorize('update', $card);

        /** @var User $user */
        $user = auth()->user();

        $validated = $request->validated();
        $workspaceIds = $validated['workspace_ids'] ?? null;
        unset($validated['workspace_ids']);

        $card->update($validated);

        if ($workspaceIds !== null) {
            $workspaceIds = array_values(array_unique(array_merge([$workspace->id], $workspaceIds)));

            app(SyncPaymentMethodWorkspacesAction::class)->execute($card, $user, $workspaceIds);
        }

        return (new CardResource($card))->response();
    }

    public function setDefault(Workspace $workspace, Card $card): JsonResponse
    {
        $this->authorize('update', $card);

        $card->setAsDefault($workspace->id);

        return (new CardResource($card))->response();
    }

    #[OA\Delete(
        path: '/workspaces/{workspace}/cards/{card}',
        tags: ['Cards'],
        summary: 'Delete a card',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'workspace', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'card', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Card deleted'),
            new OA\Response(response: 401, description: 'Unauthenticated', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(response: 404, description: 'Card not found', content: new OA\JsonContent(ref: '#/components/schemas/ApiError')),
            new OA\Response(
                response: 409,
                description: 'Card is in use (has linked expenses or fixed expenses)',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/ApiError'),
                        new OA\Schema(example: [
                            'status' => 409,
                            'code' => 'card_in_use',
                            'message' => 'The card is in use and cannot be removed.',
                        ]),
                    ]
                )
            ),
        ]
    )]
    public function destroy(Workspace $workspace, Card $card): JsonResponse
    {
        $this->authorize('delete', $card);

        if ($card->expenses()->exists() || $card->fixedExpenses()->exists()) {
            throw new DomainConflictException(
                'card_in_use',
                __('api.errors.card_in_use'),
            );
        }

        $card->delete();

        return response()->json(null, 204);
    }
}
