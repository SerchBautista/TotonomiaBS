<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Helpers\StripeWebhookHelper;
use Tests\TestCase;

class StripeWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'whsec_test_controller_secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stripe.webhook_secret' => self::WEBHOOK_SECRET]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'premium', 'guard_name' => 'api']);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * Send a raw webhook payload with a valid Stripe-Signature header.
     */
    private function postWebhook(string $payload, string $signature): \Illuminate\Testing\TestResponse
    {
        return $this->call(
            'POST',
            '/api/v1/webhooks/stripe',
            [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature],
            $payload,
        );
    }

    public function test_checkout_session_completed_with_valid_signature_returns_200_and_upgrades_user(): void
    {
        $user = User::factory()->create(['email' => 'feature-buyer@example.test']);
        $user->assignRole('user');

        $session = StripeWebhookHelper::checkoutSessionCompleted([
            'customer' => 'cus_test_feature',
            'customer_email' => $user->email,
            'customer_details' => ['email' => $user->email],
            'subscription' => null,
        ]);

        $built = StripeWebhookHelper::buildEvent('checkout.session.completed', $session);

        $response = $this->postWebhook($built['payload'], $built['signature']);

        $response->assertOk()->assertExactJson(['status' => 'ok']);

        $fresh = $user->fresh();
        $this->assertEquals('cus_test_feature', $fresh->stripe_customer_id);
        $this->assertTrue($fresh->hasRole('premium'));
    }

    public function test_subscription_deleted_with_valid_signature_returns_200_and_downgrades_user(): void
    {
        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_test_feature_delete',
            'subscription_ends_at' => now()->addDays(15),
        ]);
        $user->assignRole('user');
        $user->assignRole('premium');

        $subscription = StripeWebhookHelper::customerSubscriptionDeleted([
            'customer' => 'cus_test_feature_delete',
            'status' => 'canceled',
        ]);

        $built = StripeWebhookHelper::buildEvent('customer.subscription.deleted', $subscription);

        $response = $this->postWebhook($built['payload'], $built['signature']);

        $response->assertOk()->assertExactJson(['status' => 'ok']);

        $fresh = $user->fresh();
        $this->assertNull($fresh->subscription_ends_at);
        $this->assertFalse($fresh->hasRole('premium'));
        $this->assertTrue($fresh->hasRole('user'));
    }

    public function test_invoice_payment_succeeded_with_valid_signature_returns_200_and_creates_payment(): void
    {
        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_test_feature_invoice',
        ]);

        $invoice = StripeWebhookHelper::invoicePaymentSucceeded([
            'id' => 'in_test_feature',
            'customer' => 'cus_test_feature_invoice',
            'amount_paid' => 1999,
            'currency' => 'usd',
            'hosted_invoice_url' => 'https://invoice.stripe.com/p/feature',
            'created' => 1_700_000_500,
        ]);

        $built = StripeWebhookHelper::buildEvent('invoice.payment_succeeded', $invoice);

        $response = $this->postWebhook($built['payload'], $built['signature']);

        $response->assertOk()->assertExactJson(['status' => 'ok']);

        $this->assertDatabaseHas('subscription_payments', [
            'user_id' => $user->id,
            'amount' => 19.99,
            'currency' => 'USD',
            'status' => 'paid',
            'gateway' => 'stripe',
            'gateway_payment_id' => 'in_test_feature',
            'invoice_url' => 'https://invoice.stripe.com/p/feature',
        ]);
    }

    public function test_unhandled_event_with_valid_signature_returns_200_without_side_effects(): void
    {
        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_test_unhandled',
            'subscription_ends_at' => now()->addDays(10),
        ]);
        $user->assignRole('premium');

        $built = StripeWebhookHelper::buildEvent(
            'payment_method.attached',
            (object) ['id' => 'pm_test_unhandled', 'object' => 'payment_method'],
        );

        $response = $this->postWebhook($built['payload'], $built['signature']);

        $response->assertOk()->assertExactJson(['status' => 'ok']);

        $fresh = $user->fresh();
        // No mutation must have happened for an unhandled event type.
        $this->assertNotNull($fresh->subscription_ends_at);
        $this->assertTrue($fresh->hasRole('premium'));
        $this->assertDatabaseCount('subscription_payments', 0);
    }

    public function test_invalid_signature_returns_400(): void
    {
        $user = User::factory()->create(['email' => 'tampered@example.test']);
        $user->assignRole('user');

        $session = StripeWebhookHelper::checkoutSessionCompleted([
            'customer_email' => $user->email,
            'subscription' => null,
        ]);

        $built = StripeWebhookHelper::buildEvent('checkout.session.completed', $session);

        // Tamper with the signature.
        $tamperedSignature = 't='.time().',v1='.str_repeat('0', 64);

        $response = $this->postWebhook($built['payload'], $tamperedSignature);

        $response->assertStatus(400)
            ->assertJsonPath('code', 'stripe_invalid_signature');

        // User must NOT be upgraded.
        $this->assertFalse($user->fresh()->hasRole('premium'));
    }

    public function test_missing_signature_header_returns_400(): void
    {
        $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => []]]);

        $response = $this->call(
            'POST',
            '/api/v1/webhooks/stripe',
            [], [], [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload,
        );

        $response->assertStatus(400)
            ->assertJsonPath('code', 'stripe_webhook_not_configured');
    }

    public function test_unconfigured_webhook_secret_returns_400(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $built = StripeWebhookHelper::buildEvent(
            'checkout.session.completed',
            StripeWebhookHelper::checkoutSessionCompleted(['subscription' => null]),
            secret: 'whatever',
        );

        $response = $this->postWebhook($built['payload'], $built['signature']);

        $response->assertStatus(400)
            ->assertJsonPath('code', 'stripe_webhook_not_configured');
    }

    public function test_subscription_updated_with_valid_signature_refreshes_period_end(): void
    {
        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_test_feature_updated',
            'subscription_ends_at' => now()->subDay(),
        ]);
        $user->assignRole('premium');

        $newEnd = now()->addDays(30)->timestamp;

        $subscription = StripeWebhookHelper::customerSubscriptionUpdated([
            'customer' => 'cus_test_feature_updated',
            'status' => 'active',
            'current_period_end' => $newEnd,
        ]);

        $built = StripeWebhookHelper::buildEvent('customer.subscription.updated', $subscription);

        $response = $this->postWebhook($built['payload'], $built['signature']);

        $response->assertOk()->assertExactJson(['status' => 'ok']);

        $this->assertEquals($newEnd, $user->fresh()->subscription_ends_at->timestamp);
    }
}
