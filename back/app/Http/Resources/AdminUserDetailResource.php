<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'plan' => $this->roles->contains('name', 'premium') ? 'premium' : 'free',
            'has_active_subscription' => $this->hasActiveSubscription(),
            'subscription_ends_at' => $this->subscription_ends_at?->toISOString(),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'default_workspace_id' => $this->default_workspace_id,
            'workspaces_owned_count' => $this->ownedWorkspaces()->count(),
            'theme' => $this->theme,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'two_factor_enabled' => $this->two_factor_enabled,
            'registered_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
