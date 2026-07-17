<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserListResource extends JsonResource
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
            'registered_at' => $this->created_at?->toISOString(),
        ];
    }
}
