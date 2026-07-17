<?php

namespace App\Http\Requests\Workspace;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceMemberRequest extends FormRequest
{
    /**
     * H-013 fix: gate authorization on the `manageMembers` policy BEFORE
     * running the input rules. A viewer/editor (or a non-premium owner)
     * must get a 403 instead of a 422 `validation_error` when they send
     * input that the whitelist would reject.
     */
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        if (! $workspace) {
            return false;
        }

        return $this->user()?->can('manageMembers', $workspace) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'role' => ['sometimes', 'in:owner,guest'],
            'can_add_fixed_expenses' => ['sometimes', 'boolean'],
            'can_add_categories' => ['sometimes', 'boolean'],
        ];
    }
}
