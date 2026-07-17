<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdministratorIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 25, 50, 100])],
            'sort_by' => ['sometimes', Rule::in(['name', 'email', 'created_at'])],
            'sort_dir' => ['sometimes', Rule::in(['asc', 'desc'])],
            'search' => ['nullable', 'string', 'max:120'],
        ];
    }
}
