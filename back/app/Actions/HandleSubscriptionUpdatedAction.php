<?php

namespace App\Actions;

use App\Contracts\HandleSubscriptionUpdatedActionInterface;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class HandleSubscriptionUpdatedAction implements HandleSubscriptionUpdatedActionInterface
{
    public function execute(object $subscription): void
    {
        $customerId = $subscription->customer ?? null;

        if ($customerId === null) {
            return;
        }

        $user = User::where('stripe_customer_id', $customerId)->first();

        if ($user === null) {
            return;
        }

        $endsAt = isset($subscription->current_period_end)
            ? Carbon::createFromTimestamp($subscription->current_period_end)
            : null;

        $user->update(['subscription_ends_at' => $endsAt]);

        Log::info('Stripe subscription updated: period end refreshed.', ['user_id' => $user->id]);
    }
}
