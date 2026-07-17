<?php

namespace App\Rules;

use App\Models\Card;
use App\Models\OtherPaymentMethod;
use App\Models\Workspace;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPaymentMethodForWorkspace implements ValidationRule
{
    public function __construct(
        private readonly ?Workspace $workspace,
        private readonly string $paymentType,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (! $this->workspace instanceof Workspace) {
            $fail(__('api.validation.selected_invalid_for_current_workspace', [
                'attribute' => trans("validation.attributes.{$attribute}"),
            ]));

            return;
        }

        $modelClass = match ($this->paymentType) {
            'card' => Card::class,
            'other' => OtherPaymentMethod::class,
            default => null,
        };

        $pivotTable = match ($this->paymentType) {
            'card' => 'card_workspace',
            'other' => 'other_payment_method_workspace',
            default => null,
        };

        if ($modelClass === null || $pivotTable === null) {
            $fail(__('api.validation.selected_invalid_for_current_workspace', [
                'attribute' => trans("validation.attributes.{$attribute}"),
            ]));

            return;
        }

        $isValid = $modelClass::query()
            ->whereKey($value)
            ->whereHas('workspaces', function ($query) use ($pivotTable): void {
                $query->where('workspaces.id', $this->workspace?->id)
                    ->where($pivotTable.'.is_shared', true)
                    ->where($pivotTable.'.is_active', true);
            })
            ->exists();

        if (! $isValid) {
            $fail(__('api.validation.selected_invalid_for_current_workspace', [
                'attribute' => trans("validation.attributes.{$attribute}"),
            ]));
        }
    }
}
