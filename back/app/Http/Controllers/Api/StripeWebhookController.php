<?php

namespace App\Http\Controllers\Api;

use App\Contracts\HandleCheckoutCompletedActionInterface;
use App\Contracts\HandleInvoicePaidActionInterface;
use App\Contracts\HandleSubscriptionDeletedActionInterface;
use App\Contracts\HandleSubscriptionUpdatedActionInterface;
use App\Http\Controllers\Controller;
use App\Support\Api\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly HandleCheckoutCompletedActionInterface $onCheckoutCompleted,
        private readonly HandleSubscriptionUpdatedActionInterface $onSubscriptionUpdated,
        private readonly HandleSubscriptionDeletedActionInterface $onSubscriptionDeleted,
        private readonly HandleInvoicePaidActionInterface $onInvoicePaid,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $signature = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        if (empty($secret) || empty($signature)) {
            return ApiErrorResponse::badRequest('Webhook secret not configured.', 'stripe_webhook_not_configured');
        }

        try {
            $event = \Stripe\Webhook::constructEvent(
                $request->getContent(),
                $signature,
                $secret,
            );
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed.', [
                'error' => $e->getMessage(),
            ]);

            return ApiErrorResponse::badRequest('Invalid signature.', 'stripe_invalid_signature');
        }

        match ($event->type) {
            'checkout.session.completed' => $this->onCheckoutCompleted->execute($event->data->object),
            'customer.subscription.updated' => $this->onSubscriptionUpdated->execute($event->data->object),
            'customer.subscription.deleted' => $this->onSubscriptionDeleted->execute($event->data->object),
            'invoice.payment_succeeded' => $this->onInvoicePaid->execute($event->data->object),
            default => null,
        };

        return response()->json(['status' => 'ok']);
    }
}
