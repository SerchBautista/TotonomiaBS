<?php

namespace App\Actions;

use App\Models\Card;
use App\Models\OtherPaymentMethod;
use App\Models\Workspace;
use App\ValueObjects\BulkOperationResult;
use Illuminate\Support\Collection;

class BulkUpdateWorkspacePaymentMethodLinksAction
{
    public function __construct(
        private readonly UpdateWorkspacePaymentMethodLinkAction $updateWorkspacePaymentMethodLink,
    ) {}

    public function execute(Workspace $workspace, string $operation): BulkOperationResult
    {
        $paymentMethods = $this->paymentMethods($workspace);

        if ($operation === 'link_all') {
            foreach ($paymentMethods as $paymentMethod) {
                $this->updateWorkspacePaymentMethodLink->execute($workspace, $paymentMethod['id'], true);
            }

            return new BulkOperationResult(
                operation: $operation,
                total: $paymentMethods->count(),
                processed: $paymentMethods->count(),
                blocked: 0,
                processedIds: $paymentMethods->pluck('id')->values()->all(),
                blockedIds: [],
            );
        }

        $linkedMethods = $paymentMethods
            ->filter(fn (array $paymentMethod): bool => $paymentMethod['is_linked'] === true)
            ->values();

        foreach ($linkedMethods as $paymentMethod) {
            $this->updateWorkspacePaymentMethodLink->execute($workspace, $paymentMethod['id'], false);
        }

        return new BulkOperationResult(
            operation: $operation,
            total: $paymentMethods->count(),
            processed: $linkedMethods->count(),
            blocked: 0,
            processedIds: $linkedMethods->pluck('id')->values()->all(),
            blockedIds: [],
        );
    }

    /**
     * @return Collection<int, array{id: string, is_linked: bool}>
     */
    private function paymentMethods(Workspace $workspace): Collection
    {
        $cards = Card::query()
            ->where('user_id', $workspace->owner_id)
            ->with([
                'workspaces' => fn ($query) => $query
                    ->where('workspaces.id', $workspace->id)
                    ->select('workspaces.id'),
            ])
            ->get()
            ->map(fn (Card $card): array => [
                'id' => $card->id,
                'is_linked' => (bool) ($card->workspaces->first()?->pivot?->is_shared ?? false),
            ]);

        $otherMethods = OtherPaymentMethod::query()
            ->where('user_id', $workspace->owner_id)
            ->with([
                'workspaces' => fn ($query) => $query
                    ->where('workspaces.id', $workspace->id)
                    ->select('workspaces.id'),
            ])
            ->get()
            ->map(fn (OtherPaymentMethod $method): array => [
                'id' => $method->id,
                'is_linked' => (bool) ($method->workspaces->first()?->pivot?->is_shared ?? false),
            ]);

        return $cards->concat($otherMethods)->values();
    }
}
