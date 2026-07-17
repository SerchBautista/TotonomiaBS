<?php

namespace App\Http\Requests\PaymentMethod;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspacePaymentMethodLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'method_id' => $this->route('method'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'is_linked' => ['required', 'boolean'],
            'method_id' => [
                'required',
                'uuid',
            ],
        ];
    }
}
