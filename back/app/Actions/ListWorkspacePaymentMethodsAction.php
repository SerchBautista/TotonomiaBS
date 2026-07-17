<?php

namespace App\Actions;

use App\Models\Card;
use App\Models\OtherPaymentMethod;
use App\Models\Workspace;
use App\Support\PaymentMethod\PaymentMethodSummaryMapper;
use App\ValueObjects\PaymentMethodSummary;
use Illuminate\Support\Collection;

class ListWorkspacePaymentMethodsAction
{
    public function __construct(
        private readonly PaymentMethodSummaryMapper $mapper,
    ) {}

    /**
     * @return Collection<int, PaymentMethodSummary>
     */
    public function execute(Workspace $workspace): Collection
    {
        $cards = Card::query()
            ->where('user_id', $workspace->owner_id)
            ->with([
                'workspaces' => fn ($query) => $query
                    ->where('workspaces.id', $workspace->id)
                    ->select('workspaces.id'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Card $card): PaymentMethodSummary => $this->mapper->fromCardForWorkspace($card, $workspace));

        $otherMethods = OtherPaymentMethod::query()
            ->where('user_id', $workspace->owner_id)
            ->with([
                'workspaces' => fn ($query) => $query
                    ->where('workspaces.id', $workspace->id)
                    ->select('workspaces.id'),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (OtherPaymentMethod $method): PaymentMethodSummary => $this->mapper->fromOtherForWorkspace($method, $workspace));

        return $cards
            ->concat($otherMethods)
            ->sortBy(fn (PaymentMethodSummary $paymentMethod): string => strtolower($paymentMethod->displayName.'-'.$paymentMethod->type))
            ->values();
    }
}
