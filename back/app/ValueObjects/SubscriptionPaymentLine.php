<?php

namespace App\ValueObjects;

readonly class SubscriptionPaymentLine
{
    public function __construct(
        public string $date,
        public float $amount,
        public string $currency,
        public string $status,
        public string $gateway,
        public ?string $invoiceUrl,
    ) {}
}
