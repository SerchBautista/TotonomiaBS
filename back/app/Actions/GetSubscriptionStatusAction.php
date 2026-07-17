<?php

namespace App\Actions;

use App\Models\User;
use App\ValueObjects\SubscriptionPaymentLine;
use App\ValueObjects\SubscriptionStatus;

class GetSubscriptionStatusAction
{
    public function execute(User $user): SubscriptionStatus
    {
        $payments = $user->subscriptionPayments()
            ->limit(24)
            ->get()
            ->map(fn ($payment) => new SubscriptionPaymentLine(
                date: $payment->paid_at->toDateString(),
                amount: (float) $payment->amount,
                currency: $payment->currency,
                status: $payment->status,
                gateway: $payment->gateway,
                invoiceUrl: $payment->invoice_url,
            ))
            ->values()
            ->all();

        $hasPremiumRole = $user->hasRole('premium');
        $hasActiveSubscription = $user->hasActiveSubscription();
        $plan = ($hasPremiumRole && $hasActiveSubscription) ? 'premium' : 'free';

        return new SubscriptionStatus(
            plan: $plan,
            subscriptionEndsAt: $user->subscription_ends_at,
            payments: $payments,
        );
    }
}
