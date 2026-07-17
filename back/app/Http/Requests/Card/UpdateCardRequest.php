<?php

namespace App\Http\Requests\Card;

use App\Http\Requests\Concerns\ValidatesOwnedWorkspaceIds;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCardRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:100'],
            'card_type' => ['sometimes', 'string', 'in:credit,debit'],
            'brand' => ['sometimes', 'nullable', 'string', 'max:50'],
            'last_4_digits' => ['sometimes', 'nullable', 'string', 'digits:4'],
            'workspace_ids' => ['sometimes', 'nullable', 'array'],
            'workspace_ids.*' => ['required', 'string', 'uuid'],
        ];
    }
}
