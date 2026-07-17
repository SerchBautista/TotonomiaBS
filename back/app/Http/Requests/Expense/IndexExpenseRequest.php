<?php

namespace App\Http\Requests\Expense;

use App\Rules\ValidCategoryForWorkspace;
use Illuminate\Foundation\Http\FormRequest;

class IndexExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date', 'after_or_equal:from'],
            'category_id' => [
                'sometimes',
                'nullable',
                'uuid',
                'exists:categories,id',
                new ValidCategoryForWorkspace($this->route('workspace'), $this->user()?->id),
            ],
            'payment_type' => ['sometimes', 'nullable', 'string', 'in:cash,card,other'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
