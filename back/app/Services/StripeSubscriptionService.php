<?php

namespace App\Services;

use App\Contracts\StripeSubscriptionServiceInterface;
use Stripe\StripeClient;

class StripeSubscriptionService implements StripeSubscriptionServiceInterface
{
    private ?StripeClient $stripe = null;

    private function client(): StripeClient
    {
        return $this->stripe ??= new StripeClient(config('services.stripe.secret'));
    }

    public function retrieveSubscription(string $subscriptionId): object
    {
        return $this->client()->subscriptions->retrieve($subscriptionId);
    }
}
