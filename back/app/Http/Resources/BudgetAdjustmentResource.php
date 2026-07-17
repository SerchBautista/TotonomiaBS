<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\BudgetAdjustment */
class BudgetAdjustmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'month' => $this->month?->format('Y-m'),
            'from_category_id' => $this->from_category_id,
            'from_category' => $this->whenLoaded('fromCategory', fn () => $this->fromCategory ? new CategoryResource($this->fromCategory) : null),
            'to_category_id' => $this->to_category_id,
            'to_category' => $this->whenLoaded('toCategory', fn () => $this->toCategory ? new CategoryResource($this->toCategory) : null),
            'amount' => $this->amount,
            'reason' => $this->reason,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
