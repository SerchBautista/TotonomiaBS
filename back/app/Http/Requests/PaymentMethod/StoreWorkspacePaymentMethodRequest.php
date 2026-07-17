<?php

namespace App\Http\Requests\PaymentMethod;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkspacePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
        ];
    }
}
