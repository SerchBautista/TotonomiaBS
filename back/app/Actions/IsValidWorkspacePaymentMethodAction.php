<?php

namespace App\Actions;

use App\Models\Card;
use App\Models\OtherPaymentMethod;
use App\Models\Workspace;

class IsValidWorkspacePaymentMethodAction
{
    public function execute(Workspace $workspace, string $paymentType, ?string $paymentInstrumentId): bool
    {
        if ($paymentType === 'cash') {
            return $paymentInstrumentId === null;
        }

        if ($paymentInstrumentId === null) {
            return false;
        }

        $modelClass = match ($paymentType) {
            'card' => Card::class,
            'other' => OtherPaymentMethod::class,
            default => null,
        };

        $pivotTable = match ($paymentType) {
            'card' => 'card_workspace',
            'other' => 'other_payment_method_workspace',
            default => null,
        };

        if ($modelClass === null || $pivotTable === null) {
            return false;
        }

        return $modelClass::query()
            ->whereKey($paymentInstrumentId)
            ->whereHas('workspaces', function ($query) use ($workspace, $pivotTable): void {
                $query->where('workspaces.id', $workspace->id)
                    ->where($pivotTable.'.is_shared', true)
                    ->where($pivotTable.'.is_active', true);
            })
            ->exists();
    }
}
