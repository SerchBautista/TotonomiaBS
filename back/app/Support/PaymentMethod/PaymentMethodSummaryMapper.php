<?php

namespace App\Support\PaymentMethod;

use App\Actions\CountPaymentMethodUsageAction;
use App\Models\Card;
use App\Models\OtherPaymentMethod;
use App\Models\Workspace;
use App\ValueObjects\LinkedWorkspaceSummary;
use App\ValueObjects\PaymentMethodSummary;
use Illuminate\Support\Collection;

class PaymentMethodSummaryMapper
{
    public function __construct(
        private readonly CountPaymentMethodUsageAction $countPaymentMethodUsage,
    ) {}

    public static function maskCardDetails(?string $last4): ?string
    {
        return $last4 !== null ? '****'.substr($last4, -4) : null;
    }

    public function fromCardForUserList(Card $card): PaymentMethodSummary
    {
        return new PaymentMethodSummary(
            id: $card->id,
            type: 'card',
            name: $card->name,
            displayName: $card->name,
            maskedDetails: self::maskCardDetails($card->last_4_digits),
            linkedWorkspacesCount: (int) $card->linked_workspaces_count,
            linkedWorkspaces: $this->mapLinkedWorkspaces($card->workspaces),
        );
    }

    public function fromOtherForUserList(OtherPaymentMethod $method): PaymentMethodSummary
    {
        return new PaymentMethodSummary(
            id: $method->id,
            type: 'other',
            name: $method->name,
            displayName: $method->name,
            maskedDetails: null,
            linkedWorkspacesCount: (int) $method->linked_workspaces_count,
            linkedWorkspaces: $this->mapLinkedWorkspaces($method->workspaces),
        );
    }

    public function fromCardForWorkspace(Card $card, Workspace $workspace): PaymentMethodSummary
    {
        return $this->mapForWorkspace(
            id: $card->id,
            type: 'card',
            name: $card->name,
            maskedDetails: self::maskCardDetails($card->last_4_digits),
            paymentMethod: $card,
            workspace: $workspace,
        );
    }

    public function fromOtherForWorkspace(OtherPaymentMethod $method, Workspace $workspace): PaymentMethodSummary
    {
        return $this->mapForWorkspace(
            id: $method->id,
            type: 'other',
            name: $method->name,
            maskedDetails: null,
            paymentMethod: $method,
            workspace: $workspace,
        );
    }

    public function fromCardLinked(Card $card): PaymentMethodSummary
    {
        return new PaymentMethodSummary(
            id: $card->id,
            type: 'card',
            name: $card->name,
            displayName: $card->name,
            maskedDetails: self::maskCardDetails($card->last_4_digits),
            isLinked: true,
            isValidForTransactions: true,
            state: 'linked',
        );
    }

    public function fromOtherLinked(OtherPaymentMethod $method): PaymentMethodSummary
    {
        return new PaymentMethodSummary(
            id: $method->id,
            type: 'other',
            name: $method->name,
            displayName: $method->name,
            maskedDetails: null,
            isLinked: true,
            isValidForTransactions: true,
            state: 'linked',
        );
    }

    public function fromCardValid(Card $card): PaymentMethodSummary
    {
        return new PaymentMethodSummary(
            id: $card->id,
            type: 'card',
            name: $card->name,
            displayName: $card->name,
            maskedDetails: self::maskCardDetails($card->last_4_digits),
            isLinked: true,
            isValidForTransactions: true,
            state: 'linked',
        );
    }

    public function fromOtherValid(OtherPaymentMethod $method): PaymentMethodSummary
    {
        return new PaymentMethodSummary(
            id: $method->id,
            type: 'other',
            name: $method->name,
            displayName: $method->name,
            maskedDetails: null,
            isLinked: true,
            isValidForTransactions: true,
            state: 'linked',
        );
    }

    public function cashDefault(): PaymentMethodSummary
    {
        return new PaymentMethodSummary(
            id: null,
            type: 'cash',
            name: 'Efectivo',
            displayName: 'Efectivo',
            maskedDetails: null,
            isLinked: false,
            isValidForTransactions: true,
            state: 'always_available',
        );
    }

    public function fromModel(Card|OtherPaymentMethod $paymentMethod): PaymentMethodSummary
    {
        return $paymentMethod instanceof Card
            ? $this->fromCardLinked($paymentMethod)
            : $this->fromOtherLinked($paymentMethod);
    }

    /**
     * @param  Collection<int, Workspace>  $workspaces
     * @return array<int, LinkedWorkspaceSummary>
     */
    private function mapLinkedWorkspaces(Collection $workspaces): array
    {
        return $workspaces
            ->map(fn (Workspace $workspace): LinkedWorkspaceSummary => new LinkedWorkspaceSummary(
                id: $workspace->id,
                name: $workspace->name,
            ))
            ->values()
            ->all();
    }

    private function mapForWorkspace(
        string $id,
        string $type,
        string $name,
        ?string $maskedDetails,
        Card|OtherPaymentMethod $paymentMethod,
        Workspace $workspace,
    ): PaymentMethodSummary {
        $workspaceLink = $paymentMethod->workspaces->first();
        $hasPivot = $workspaceLink !== null;
        $isLinked = (bool) ($workspaceLink?->pivot?->is_shared ?? false);
        $isActive = $isLinked ? (bool) ($workspaceLink?->pivot?->is_active ?? true) : false;
        $isInUse = $this->countPaymentMethodUsage->execute($paymentMethod, $workspace) > 0;

        return new PaymentMethodSummary(
            id: $id,
            type: $type,
            name: $name,
            displayName: $name,
            maskedDetails: $maskedDetails,
            isLinked: $isLinked,
            isValidForTransactions: $isLinked && $isActive,
            state: $this->resolveState($hasPivot, $isLinked, $isActive),
            isInUseInWorkspace: $isInUse,
        );
    }

    private function resolveState(bool $hasPivot, bool $isLinked, bool $isActive): string
    {
        if ($isLinked && $isActive) {
            return 'linked';
        }

        if ($hasPivot) {
            return 'read_only_linked';
        }

        return 'not_linked';
    }
}
