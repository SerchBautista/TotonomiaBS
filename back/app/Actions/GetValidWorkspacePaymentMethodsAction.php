<?php

namespace App\Actions;

use App\Models\Card;
use App\Models\OtherPaymentMethod;
use App\Models\Workspace;
use App\Support\PaymentMethod\PaymentMethodSummaryMapper;
use App\ValueObjects\PaymentMethodSummary;
use Illuminate\Support\Collection;

class GetValidWorkspacePaymentMethodsAction
{
    public function __construct(
        private readonly PaymentMethodSummaryMapper $mapper,
    ) {}

    /**
     * @return Collection<int, PaymentMethodSummary>
     */
    public function execute(Workspace $workspace): Collection
    {
        $cash = collect([$this->mapper->cashDefault()]);

        $cards = Card::query()
            ->where('user_id', $workspace->owner_id)
            ->whereHas('workspaces', function ($query) use ($workspace): void {
                $query->where('workspaces.id', $workspace->id)
                    ->where('card_workspace.is_shared', true)
                    ->where('card_workspace.is_active', true);
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Card $card): PaymentMethodSummary => $this->mapper->fromCardValid($card));

        $otherMethods = OtherPaymentMethod::query()
            ->where('user_id', $workspace->owner_id)
            ->whereHas('workspaces', function ($query) use ($workspace): void {
                $query->where('workspaces.id', $workspace->id)
                    ->where('other_payment_method_workspace.is_shared', true)
                    ->where('other_payment_method_workspace.is_active', true);
            })
            ->orderBy('name')
            ->get()
            ->map(fn (OtherPaymentMethod $method): PaymentMethodSummary => $this->mapper->fromOtherValid($method));

        return $cash
            ->concat($cards)
            ->concat($otherMethods)
            ->sortBy(fn (PaymentMethodSummary $paymentMethod): string => strtolower($paymentMethod->displayName.'-'.$paymentMethod->type))
            ->values();
    }
}
