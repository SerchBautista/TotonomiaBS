<?php

namespace App\Actions;

use App\Exceptions\DomainNotFoundException;
use App\Models\Card;
use App\Models\OtherPaymentMethod;
use App\Models\Workspace;

class UpdateWorkspacePaymentMethodLinkAction
{
    public function __construct(
        private readonly CountPaymentMethodUsageAction $countPaymentMethodUsage,
    ) {}

    public function execute(Workspace $workspace, string $methodId, bool $isLinked): void
    {
        $paymentMethod = $this->resolvePaymentMethod($workspace, $methodId);

        if ($isLinked) {
            $paymentMethod->workspaces()->syncWithoutDetaching([
                $workspace->id => ['is_shared' => true, 'is_active' => true],
            ]);

            return;
        }

        $usageCount = $this->countPaymentMethodUsage->execute($paymentMethod, $workspace);

        if ($usageCount > 0) {
            $paymentMethod->workspaces()->syncWithoutDetaching([
                $workspace->id => ['is_shared' => false, 'is_active' => false],
            ]);

            return;
        }

        $paymentMethod->workspaces()->detach($workspace->id);
    }

    private function resolvePaymentMethod(Workspace $workspace, string $methodId): Card|OtherPaymentMethod
    {
        $card = Card::query()
            ->where('user_id', $workspace->owner_id)
            ->find($methodId);

        if ($card instanceof Card) {
            return $card;
        }

        $otherMethod = OtherPaymentMethod::query()
            ->where('user_id', $workspace->owner_id)
            ->find($methodId);

        if ($otherMethod instanceof OtherPaymentMethod) {
            return $otherMethod;
        }

        throw new DomainNotFoundException(
            'workspace_payment_method_not_found',
            'Workspace payment method not found.',
        );
    }
}
