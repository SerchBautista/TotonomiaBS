<?php

namespace App\Http\Resources;

use App\Models\Card;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Expense */
class ExpenseResource extends JsonResource
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
            'date' => $this->date?->toDateString(),
            'description' => $this->description,
            'category' => $this->whenLoaded('category', fn () => $this->category ? new CategoryResource($this->category) : null),
            'payment_type' => $this->payment_type,
            'payment_instrument' => $instrumentResource,
            'user' => $this->whenLoaded('user', fn () => $this->user ? new UserResource($this->user) : null),
            'paid_by_user_id' => $this->paid_by_user_id,
            'paid_by' => $this->whenLoaded('paidBy', fn () => $this->paidBy ? new UserResource($this->paidBy) : null),
            'fixed_expense_id' => $this->fixed_expense_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
