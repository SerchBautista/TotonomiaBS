<?php

namespace App\Actions;

use App\Contracts\HandleCheckoutCompletedActionInterface;
use App\Contracts\StripeSubscriptionServiceInterface;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class HandleCheckoutCompletedAction implements HandleCheckoutCompletedActionInterface
{
    public function __construct(
        private readonly StripeSubscriptionServiceInterface $stripeSubscriptions,
    ) {}

    public function execute(object $session): void
    {
        $customerId = $session->customer ?? null;
        $customerEmail = $session->customer_email ?? $session->customer_details?->email ?? null;
        $subscriptionId = $session->subscription ?? null;

        if ($customerEmail === null) {
            return;
        }

        $user = User::where('email', $customerEmail)->first();

        if ($user === null) {
            return;
        }

        $endsAt = null;

        if ($subscriptionId) {
            try {
                $subscription = $this->stripeSubscriptions->retrieveSubscription($subscriptionId);
                $endsAt = Carbon::createFromTimestamp($subscription->current_period_end);
            } catch (\Exception $e) {
                Log::warning('Could not retrieve Stripe subscription period end.', ['error' => $e->getMessage()]);
            }
        }

        $user->update([
            'stripe_customer_id' => $customerId,
            'subscription_ends_at' => $endsAt,
        ]);
        $user->syncRoles(['user', 'premium']);

        Log::info('Stripe checkout completed: user upgraded to premium.', ['user_id' => $user->id]);
    }
}
