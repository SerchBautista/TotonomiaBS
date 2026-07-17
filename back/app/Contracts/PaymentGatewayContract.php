<?php

namespace App\Contracts;

use App\Models\User;
use App\ValueObjects\CheckoutSession;

interface PaymentGatewayContract
{
    public function createCheckoutSession(User $user): CheckoutSession;
}
