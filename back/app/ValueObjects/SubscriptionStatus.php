<?php

namespace App\ValueObjects;

use Illuminate\Support\Carbon;

readonly class SubscriptionStatus
{
    /**
     * @param  array<int, SubscriptionPaymentLine>  $payments
     */
    public function __construct(
        public string $plan,
        public ?Carbon $subscriptionEndsAt,
        public array $payments,
    ) {}
}
