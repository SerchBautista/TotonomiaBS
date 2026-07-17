<?php

namespace App\Http\Resources;

use App\Models\Card;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\FixedExpenseOccurrence */
class OccurrenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fixedExpense = $this->whenLoaded('fixedExpense');

        $suggestedInstrument = null;
        if ($fixedExpense && $fixedExpense->relationLoaded('paymentInstrument') && $fixedExpense->paymentInstrument !== null) {
            $suggestedInstrument = $fixedExpense->paymentInstrument instanceof Card
                ? new CardResource($fixedExpense->paymentInstrument)
                : new OtherPaymentMethodResource($fixedExpense->paymentInstrument);
        }

        return [
            'id' => $this->id,
            'due_date' => $this->due_date?->toDateString(),
            'suggested_amount' => $this->suggested_amount,
            'status' => $this->status,
            'fixed_expense' => $fixedExpense ? [
                'id' => $fixedExpense->id,
                'description' => $fixedExpense->description,
                'frequency' => $fixedExpense->frequency,
                'payment_type' => $fixedExpense->payment_type,
                'payment_instrument' => $suggestedInstrument,
                'category' => $fixedExpense->relationLoaded('category') && $fixedExpense->category
                    ? new CategoryResource($fixedExpense->category)
                    : null,
            ] : null,
        ];
    }
}
