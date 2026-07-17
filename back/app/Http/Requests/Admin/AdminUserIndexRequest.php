<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUserIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 25, 50, 100])],
            'search' => ['nullable', 'string', 'max:120'],
            'sort_by' => ['sometimes', Rule::in(['name', 'email', 'created_at', 'registered_at', 'subscription_ends_at'])],
            'sort_dir' => ['sometimes', Rule::in(['asc', 'desc'])],
            'plan' => ['sometimes', Rule::in(['free', 'premium'])],
            'subscription_status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'email_verified' => ['sometimes', Rule::in(['verified', 'unverified'])],
        ];
    }
}
