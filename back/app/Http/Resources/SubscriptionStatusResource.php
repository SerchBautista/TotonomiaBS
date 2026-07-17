<?php

namespace App\Http\Resources;

use App\ValueObjects\SubscriptionPaymentLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\ValueObjects\SubscriptionStatus */
class SubscriptionStatusResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'plan' => $this->plan,
            'subscription_ends_at' => $this->subscriptionEndsAt?->toIso8601String(),
            'payments' => array_map(
                fn (SubscriptionPaymentLine $payment) => [
                    'date' => $payment->date,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'gateway' => $payment->gateway,
                    'invoice_url' => $payment->invoiceUrl,
                ],
                $this->payments,
            ),
        ];
    }
}
