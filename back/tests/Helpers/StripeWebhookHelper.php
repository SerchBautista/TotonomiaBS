<?php

namespace Tests\Helpers;

use Stripe\Webhook;
use Stripe\WebhookSignature;

/**
 * Builds signed Stripe webhook payloads for use in tests.
 *
 * The fixtures here mirror the small subset of fields the application
 * actually reads from each event object (checkout.session.completed,
 * customer.subscription.updated/deleted and invoice.payment_succeeded).
 * Adding more fields is fine, but the helpers must stay aligned with the
 * actions in `app/Actions/*` so the tests fail loudly if the actions
 * start expecting new metadata.
 */
final class StripeWebhookHelper
{
    public const SIGNATURE_SCHEME = 'v1';

    /**
     * Build the JSON payload + signature pair Stripe would POST to
     * `/api/v1/webhooks/stripe` for a given event type.
     *
     * @param  string  $type  Event type (e.g. 'checkout.session.completed').
     * @param  object|array  $dataObject  The `data.object` body. Use the
     *                                    per-event builders below for realistic fixtures.
     * @param  int|null  $timestamp  Optional override; defaults to now().
     * @param  string|null  $secret  Optional override; defaults to
     *                               config('services.stripe.webhook_secret').
     * @return array{payload: string, signature: string, event: \Stripe\Event}
     */
    public static function buildEvent(
        string $type,
        object|array $dataObject,
        ?int $timestamp = null,
        ?string $secret = null,
    ): array {
        $timestamp ??= time();
        $secret ??= (string) config('services.stripe.webhook_secret');

        $event = [
            'id' => 'evt_test_'.bin2hex(random_bytes(8)),
            'object' => 'event',
            'api_version' => '2024-06-20',
            'created' => $timestamp,
            'type' => $type,
            'livemode' => false,
            'pending_webhooks' => 1,
            'request' => [
                'id' => null,
                'idempotency_key' => null,
            ],
            'data' => [
                'object' => is_object($dataObject) ? self::objectToArray($dataObject) : $dataObject,
            ],
        ];

        $payload = json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $signature = self::sign($payload, $timestamp, $secret);

        return [
            'payload' => $payload,
            'signature' => $signature,
            'event' => Webhook::constructEvent($payload, $signature, $secret, 0),
        ];
    }

    /**
     * Sign `$payload` with `$secret` and return the `Stripe-Signature`
     * header value (`t=<timestamp>,v1=<hmac_sha256(timestamp.payload, secret)>`).
     */
    public static function sign(string $payload, int $timestamp, string $secret): string
    {
        $signedPayload = $timestamp.'.'.$payload;
        $hash = hash_hmac('sha256', $signedPayload, $secret);

        return sprintf('t=%d,%s=%s', $timestamp, self::SIGNATURE_SCHEME, $hash);
    }

    /**
     * Realistic fixture for a `checkout.session.completed` payload.
     *
     * @param  array<string, mixed>  $overrides  Field overrides merged on top
     *                                           of the defaults (e.g. `customer_email`).
     * @return object The session object suitable for `data.object`.
     */
    public static function checkoutSessionCompleted(array $overrides = []): object
    {
        $defaults = [
            'id' => 'cs_test_'.bin2hex(random_bytes(8)),
            'object' => 'checkout.session',
            'mode' => 'subscription',
            'status' => 'complete',
            'customer' => 'cus_test_'.bin2hex(random_bytes(8)),
            'customer_email' => 'checkout@example.test',
            'customer_details' => ['email' => 'checkout@example.test'],
            'subscription' => 'sub_test_'.bin2hex(random_bytes(8)),
            'amount_total' => 999,
            'currency' => 'usd',
            'payment_status' => 'paid',
        ];

        return (object) array_merge($defaults, $overrides);
    }

    /**
     * Realistic fixture for a `customer.subscription.updated` payload.
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function customerSubscriptionUpdated(array $overrides = []): object
    {
        $periodEnd = time() + 60 * 60 * 24 * 30;

        $defaults = [
            'id' => 'sub_test_'.bin2hex(random_bytes(8)),
            'object' => 'subscription',
            'status' => 'active',
            'customer' => 'cus_test_'.bin2hex(random_bytes(8)),
            'current_period_start' => time(),
            'current_period_end' => $periodEnd,
            'cancel_at_period_end' => false,
            'items' => [
                'object' => 'list',
                'data' => [[
                    'object' => 'subscription_item',
                    'id' => 'si_test_'.bin2hex(random_bytes(8)),
                ]],
            ],
        ];

        return (object) array_merge($defaults, $overrides);
    }

    /**
     * Realistic fixture for a `customer.subscription.deleted` payload.
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function customerSubscriptionDeleted(array $overrides = []): object
    {
        return self::customerSubscriptionUpdated(array_merge([
            'status' => 'canceled',
        ], $overrides));
    }

    /**
     * Realistic fixture for an `invoice.payment_succeeded` payload.
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function invoicePaymentSucceeded(array $overrides = []): object
    {
        $created = time();

        $defaults = [
            'id' => 'in_test_'.bin2hex(random_bytes(8)),
            'object' => 'invoice',
            'status' => 'paid',
            'customer' => 'cus_test_'.bin2hex(random_bytes(8)),
            'customer_email' => 'invoice@example.test',
            'amount_due' => 999,
            'amount_paid' => 999,
            'amount_remaining' => 0,
            'currency' => 'usd',
            'created' => $created,
            'hosted_invoice_url' => 'https://invoice.stripe.com/p/test',
            'invoice_pdf' => 'https://invoice.stripe.com/p/test/pdf',
            'subscription' => 'sub_test_'.bin2hex(random_bytes(8)),
        ];

        return (object) array_merge($defaults, $overrides);
    }

    /**
     * Verify a signature against a payload + secret, using the same
     * algorithm Stripe uses. Useful for negative-path tests that want
     * to confirm a tampered signature is rejected.
     */
    public static function verify(string $payload, string $signature, string $secret): bool
    {
        try {
            WebhookSignature::verifyHeader($payload, $signature, $secret, 0);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Convert a nested stdClass tree into an associative array so it
     * round-trips through json_encode without losing properties.
     */
    private static function objectToArray(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            return array_map([self::class, 'objectToArray'], (array) $value);
        }

        if (is_array($value)) {
            return array_map([self::class, 'objectToArray'], $value);
        }

        return $value;
    }
}
