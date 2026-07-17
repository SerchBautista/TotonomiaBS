<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SetDefaultWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'workspace_id' => ['required', 'uuid', 'exists:workspaces,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            /** @var \App\Models\User $user */
            $user = $this->user();
            $workspaceId = $this->input('workspace_id');

            if ($workspaceId && ! $user->workspaces()->where('workspaces.id', $workspaceId)->exists()) {
                $v->errors()->add('workspace_id', 'You are not a member of this workspace.');
            }
        });
    }
}
