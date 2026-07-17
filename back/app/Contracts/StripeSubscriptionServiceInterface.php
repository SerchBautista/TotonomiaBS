<?php

namespace App\Contracts;

interface StripeSubscriptionServiceInterface
{
    public function retrieveSubscription(string $subscriptionId): object;
}
