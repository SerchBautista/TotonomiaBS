<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Category */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'icon' => $this->icon,
            'color' => $this->color,
            'is_default' => (bool) $this->is_default,
            'is_linked' => $this->when(
                array_key_exists('is_linked', $this->resource->getAttributes()),
                fn () => (bool) $this->resource->getAttribute('is_linked')
            ),
            'is_active_in_workspace' => $this->when(
                array_key_exists('is_active_in_workspace', $this->resource->getAttributes()),
                fn () => (bool) $this->resource->getAttribute('is_active_in_workspace')
            ),
            'is_in_use_in_workspace' => $this->when(
                array_key_exists('is_in_use_in_workspace', $this->resource->getAttributes()),
                fn () => (bool) $this->resource->getAttribute('is_in_use_in_workspace')
            ),
            'is_valid_for_transactions' => $this->when(
                array_key_exists('is_valid_for_transactions', $this->resource->getAttributes()),
                fn () => (bool) $this->resource->getAttribute('is_valid_for_transactions')
            ),
            'state' => $this->when(
                array_key_exists('state', $this->resource->getAttributes()),
                fn () => (string) $this->resource->getAttribute('state')
            ),
            'linked_workspaces_count' => $this->when(
                array_key_exists('linked_workspaces_count', $this->resource->getAttributes()),
                fn () => (int) $this->resource->getAttribute('linked_workspaces_count')
            ),
            'linked_workspaces' => $this->when(
                array_key_exists('linked_workspaces', $this->resource->getAttributes()),
                fn () => $this->resource->getAttribute('linked_workspaces')
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
