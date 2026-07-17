<?php

namespace App\Http\Requests\BudgetAdjustment;

use App\Rules\ValidCategoryForWorkspace;
use Illuminate\Foundation\Http\FormRequest;

class AvailableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'month' => ['sometimes', 'nullable', 'date_format:Y-m'],
            'exclude_category_id' => [
                'sometimes',
                'nullable',
                'uuid',
                'exists:categories,id',
                new ValidCategoryForWorkspace($this->route('workspace'), $this->user()?->id),
            ],
        ];
    }
}
