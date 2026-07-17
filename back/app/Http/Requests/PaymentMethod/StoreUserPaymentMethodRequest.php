<?php

namespace App\Http\Requests\PaymentMethod;

use App\Http\Requests\Concerns\ValidatesOwnedWorkspaceIds;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserPaymentMethodRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(['card', 'other'])],
            'name' => ['required', 'string', 'max:100'],
            'card_type' => [
                Rule::requiredIf(fn (): bool => $this->input('type') === 'card'),
                'nullable',
                'string',
                Rule::in(['credit', 'debit']),
            ],
            'brand' => ['sometimes', 'nullable', 'string', 'max:50'],
            'last_4_digits' => [
                Rule::requiredIf(fn (): bool => $this->input('type') === 'card'),
                'nullable',
                'string',
                'digits:4',
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'workspace_ids' => ['sometimes', 'nullable', 'array'],
            'workspace_ids.*' => ['required', 'string', 'uuid'],
        ];
    }
}
