<?php

namespace App\Http\Requests\Category;

use App\Http\Requests\Concerns\ValidatesOwnedWorkspaceIds;
use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    use ValidatesOwnedWorkspaceIds;

    public function authorize(): bool
    {
        return $this->authorizeOwnedWorkspaceIds();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:50'],
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'workspace_ids' => ['sometimes', 'nullable', 'array'],
            'workspace_ids.*' => ['required', 'string', 'uuid'],
        ];
    }
}
