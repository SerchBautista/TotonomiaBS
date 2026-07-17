<?php

namespace App\Actions;

use App\Contracts\AssignUserPlanActionInterface;
use App\Contracts\PaymentGatewayContract;
use App\Exceptions\DomainConflictException;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\ValueObjects\CheckoutSession;

class InitiateCheckoutAction
{
    public function __construct(
        private readonly PaymentGatewayContract $gateway,
        private readonly AssignUserPlanActionInterface $assignPlan,
    ) {}

    public function execute(User $user): CheckoutSession
    {
        if ($user->hasRole('premium')) {
            throw new DomainConflictException(
                'subscription_already_active',
                __('api.errors.subscription_already_active'),
            );
        }

        $session = $this->gateway->createCheckoutSession($user);

        if ($session->isDummy) {
            $this->assignPlan->execute($user, 'premium');

            SubscriptionPayment::create([
                'user_id' => $user->id,
                'amount' => 0.00,
                'currency' => 'USD',
                'status' => 'paid',
                'gateway' => 'dummy',
                'paid_at' => now(),
            ]);
        }

        return $session;
    }
}
