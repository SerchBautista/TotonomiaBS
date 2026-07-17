<?php

namespace App\Services;

use App\Contracts\PaymentGatewayContract;
use App\Models\User;
use App\ValueObjects\CheckoutSession;
use Stripe\StripeClient;

class StripeGatewayService implements PaymentGatewayContract
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function createCheckoutSession(User $user): CheckoutSession
    {
        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'subscription',
            'customer_email' => $user->email,
            'line_items' => [[
                'price' => config('services.stripe.premium_price_id'),
                'quantity' => 1,
            ]],
            'success_url' => config('app.frontend_url').'/pricing/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('app.frontend_url').'/pricing',
        ]);

        return new CheckoutSession(
            url: $session->url,
            isDummy: false,
        );
    }
}
