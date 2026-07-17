<?php

namespace App\Actions;

use App\Contracts\HandleSubscriptionDeletedActionInterface;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class HandleSubscriptionDeletedAction implements HandleSubscriptionDeletedActionInterface
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

        $user->update(['subscription_ends_at' => null]);
        $user->syncRoles(['user']);

        Log::info('Stripe subscription cancelled: user downgraded to free.', ['user_id' => $user->id]);
    }
}
