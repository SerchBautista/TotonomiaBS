<?php

namespace App\Services;

use App\Contracts\PaymentGatewayContract;
use App\Models\User;
use App\ValueObjects\CheckoutSession;
use Illuminate\Support\Facades\Log;

class DummyGatewayService implements PaymentGatewayContract
{
    public function __construct()
    {
        Log::warning('DummyGatewayService activo — no usar en producción');
    }

    public function createCheckoutSession(User $user): CheckoutSession
    {
        return new CheckoutSession(
            url: '/pricing/success?dummy=true',
            isDummy: true,
        );
    }
}
