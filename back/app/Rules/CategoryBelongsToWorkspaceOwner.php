<?php

namespace App\Rules;

use App\Models\Category;
use App\Models\Workspace;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CategoryBelongsToWorkspaceOwner implements ValidationRule
{
    public function __construct(
        private readonly ?Workspace $workspace,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->workspace || ! is_string($value) || $value === '') {
            return;
        }

        $belongsToOwner = Category::query()
            ->where('id', $value)
            ->where('user_id', $this->workspace->owner_id)
            ->exists();

        if (! $belongsToOwner) {
            $fail(__('api.validation.selected_invalid_for_workspace', [
                'attribute' => trans("validation.attributes.{$attribute}"),
            ]));
        }
    }
}
