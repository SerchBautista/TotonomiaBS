<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Card */
class CardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'name' => $this->name,
            'card_type' => $this->card_type,
            'brand' => $this->brand,
            'last_4_digits' => $this->when(
                $this->last_4_digits !== null,
                fn () => '****'.substr($this->last_4_digits, -4)
            ),
            'is_default' => (bool) $this->is_default,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
