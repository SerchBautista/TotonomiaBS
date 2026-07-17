<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class WorkspaceMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->pivot->role,
            'can_add_fixed_expenses' => (bool) $this->pivot->can_add_fixed_expenses,
            'can_add_categories' => (bool) $this->pivot->can_add_categories,
        ];
    }
}
