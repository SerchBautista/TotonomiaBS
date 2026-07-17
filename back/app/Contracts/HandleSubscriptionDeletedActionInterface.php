<?php

namespace App\Contracts;

interface HandleSubscriptionDeletedActionInterface
{
    public function execute(object $subscription): void;
}
