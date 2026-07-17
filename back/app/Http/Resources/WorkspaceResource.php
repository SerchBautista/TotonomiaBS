<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Workspace */
class WorkspaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'name' => $this->name,
            'type' => $this->type,
            'currency_code' => $this->currency_code,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? new UserResource($this->owner) : null),
            'owner_plan' => $this->whenLoaded('owner', fn () => $this->ownerHasPremium() ? 'premium' : 'free'),
            'members_count' => $this->whenCounted('members'),
            'current_user_permissions' => $this->resolveCurrentUserPermissions($request),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function resolveCurrentUserPermissions(Request $request): array
    {
        $user = $request->user();

        if (! $user) {
            return ['can_add_fixed_expenses' => false, 'can_add_categories' => false];
        }

        if ($this->owner_id === $user->id) {
            return ['can_add_fixed_expenses' => true, 'can_add_categories' => true];
        }

        $pivot = $this->members()->where('user_id', $user->id)->first()?->pivot;

        return [
            'can_add_fixed_expenses' => (bool) ($pivot?->can_add_fixed_expenses ?? false),
            'can_add_categories' => (bool) ($pivot?->can_add_categories ?? false),
        ];
    }
}
