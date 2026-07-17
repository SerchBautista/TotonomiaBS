<?php

namespace App\Contracts;

interface HandleSubscriptionUpdatedActionInterface
{
    public function execute(object $subscription): void;
}
