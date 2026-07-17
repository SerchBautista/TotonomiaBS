<?php

namespace App\Http\Requests\Category;

use App\Rules\CategoryBelongsToWorkspaceOwner;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryActivationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'category_id' => $this->route('category')?->id,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
            'category_id' => [
                'required',
                'uuid',
                new CategoryBelongsToWorkspaceOwner($this->route('workspace')),
            ],
        ];
    }
}
