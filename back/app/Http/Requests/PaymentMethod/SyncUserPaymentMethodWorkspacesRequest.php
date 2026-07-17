<?php

namespace App\Http\Requests\PaymentMethod;

use App\Http\Requests\Concerns\ValidatesOwnedWorkspaceIds;
use Illuminate\Foundation\Http\FormRequest;

class SyncUserPaymentMethodWorkspacesRequest extends FormRequest
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
            'workspace_ids' => ['present', 'array'],
            'workspace_ids.*' => ['required', 'string', 'uuid'],
        ];
    }
}
