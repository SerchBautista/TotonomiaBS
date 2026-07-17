<?php

namespace App\Http\Requests\Category;

use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateCategorySharingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        /** @var Workspace|null $workspace */
        $workspace = $this->route('workspace');

        $this->merge([
            'workspace_id' => $workspace?->id,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'operation' => ['required', 'string', 'in:link_all,unlink_all'],
            'workspace_id' => ['required', 'uuid'],
        ];
    }
}
