<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            // Prioritize privilege order when a user has more than one role
            // (e.g. ["user", "admin"]). getRoleNames()->first() alone would return
            // the role that happens to come first in the SQL query, which can
            // misclassify an admin as a regular user and break admin login on
            // the front end. The admin endpoint already filters by
            // hasRole('admin') at the authenticator level, so this is purely
            // a display priority for the JSON payload.
            'role' => collect(['admin', 'user'])
                ->first(fn (string $role): bool => $this->hasRole($role))
                ?? $this->getRoleNames()->first()
                ?? 'user',
            'plan' => $this->hasRole('premium') ? 'premium' : 'free',
            'default_workspace_id' => $this->default_workspace_id,
            'theme' => $this->theme,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'two_factor_enabled' => $this->two_factor_enabled,
            'permissions' => $this->getAllPermissions()->pluck('name')->toArray(),
        ];
    }
}
