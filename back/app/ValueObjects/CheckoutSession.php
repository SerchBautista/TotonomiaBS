<?php

namespace App\ValueObjects;

readonly class CheckoutSession
{
    public function __construct(
        public string $url,
        public bool $isDummy,
    ) {}
}
