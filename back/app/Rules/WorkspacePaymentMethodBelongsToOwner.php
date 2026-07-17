<?php

namespace App\Rules;

use App\Models\Card;
use App\Models\OtherPaymentMethod;
use App\Models\Workspace;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class WorkspacePaymentMethodBelongsToOwner implements ValidationRule
{
    public function __construct(
        private readonly ?Workspace $workspace,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->workspace instanceof Workspace) {
            $fail(__('api.validation.selected_invalid', [
                'attribute' => trans("validation.attributes.{$attribute}"),
            ]));

            return;
        }

        $exists = Card::query()
            ->where('user_id', $this->workspace->owner_id)
            ->whereKey($value)
            ->exists()
            || OtherPaymentMethod::query()
                ->where('user_id', $this->workspace->owner_id)
                ->whereKey($value)
                ->exists();

        if (! $exists) {
            $fail(__('api.validation.selected_invalid', [
                'attribute' => trans("validation.attributes.{$attribute}"),
            ]));
        }
    }
}
