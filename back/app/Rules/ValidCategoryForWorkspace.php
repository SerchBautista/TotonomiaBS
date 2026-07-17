<?php

namespace App\Rules;

use App\Actions\GetValidWorkspaceCategoriesAction;
use App\Models\Workspace;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCategoryForWorkspace implements ValidationRule
{
    public function __construct(
        private readonly ?Workspace $workspace,
        private readonly ?string $userId,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->workspace || ! $this->userId || ! is_string($value) || $value === '') {
            return;
        }

        $isValid = app(GetValidWorkspaceCategoriesAction::class)
            ->execute($this->workspace)
            ->where('id', $value)
            ->exists();

        if (! $isValid) {
            $fail(__('api.validation.selected_invalid_for_current_workspace', [
                'attribute' => trans("validation.attributes.{$attribute}"),
            ]));
        }
    }
}
