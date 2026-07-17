<?php

namespace App\Actions;

use App\Models\Card;
use App\Models\OtherPaymentMethod;
use App\Models\User;
use App\Support\PaymentMethod\PaymentMethodSummaryMapper;
use App\ValueObjects\PaymentMethodSummary;
use Illuminate\Support\Collection;

class ListUserPaymentMethodsAction
{
    public function __construct(
        private readonly PaymentMethodSummaryMapper $mapper,
    ) {}

    /**
     * @return Collection<int, PaymentMethodSummary>
     */
    public function execute(User $user): Collection
    {
        $cards = Card::query()
            ->where('user_id', $user->id)
            ->with([
                'workspaces' => fn ($query) => $query
                    ->where('card_workspace.is_shared', true)
                    ->select('workspaces.id', 'workspaces.name'),
            ])
            ->withCount([
                'workspaces as linked_workspaces_count' => fn ($query) => $query->where('card_workspace.is_shared', true),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Card $card): PaymentMethodSummary => $this->mapper->fromCardForUserList($card));

        $otherMethods = OtherPaymentMethod::query()
            ->where('user_id', $user->id)
            ->with([
                'workspaces' => fn ($query) => $query
                    ->where('other_payment_method_workspace.is_shared', true)
                    ->select('workspaces.id', 'workspaces.name'),
            ])
            ->withCount([
                'workspaces as linked_workspaces_count' => fn ($query) => $query->where('other_payment_method_workspace.is_shared', true),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (OtherPaymentMethod $method): PaymentMethodSummary => $this->mapper->fromOtherForUserList($method));

        return $cards
            ->concat($otherMethods)
            ->sortBy(fn (PaymentMethodSummary $paymentMethod): string => strtolower($paymentMethod->displayName.'-'.$paymentMethod->type))
            ->values();
    }
}
