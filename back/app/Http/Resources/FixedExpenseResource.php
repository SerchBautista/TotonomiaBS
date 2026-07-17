<?php

namespace App\Http\Resources;

use App\Models\Card;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\FixedExpense */
class FixedExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $instrumentResource = null;
        if ($this->relationLoaded('paymentInstrument') && $this->paymentInstrument !== null) {
            $instrumentResource = $this->paymentInstrument instanceof Card
                ? new CardResource($this->paymentInstrument)
                : new OtherPaymentMethodResource($this->paymentInstrument);
        }

        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'amount' => $this->amount,
            'description' => $this->description,
            'frequency' => $this->frequency,
            'next_due_date' => $this->next_due_date?->toDateString(),
            'alert_date' => $this->alert_date?->toDateString(),
            'is_active' => $this->is_active,
            'category' => $this->whenLoaded('category', fn () => $this->category ? new CategoryResource($this->category) : null),
            'payment_type' => $this->payment_type,
            'payment_instrument' => $instrumentResource,
            'type' => $this->type ?? 'recurring',
            'total_installments' => $this->total_installments,
            'remaining_installments' => $this->remaining_installments,
            'has_paid_occurrences' => $this->occurrences()->where('status', 'paid')->exists(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
