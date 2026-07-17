<?php

namespace Tests\Unit\Actions;

use App\Actions\HandleInvoicePaidAction;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Helpers\StripeWebhookHelper;
use Tests\TestCase;

class HandleInvoicePaidActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'premium', 'guard_name' => 'api']);
    }

    public function test_invoice_paid_creates_subscription_payment_record(): void
    {
        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_test_invoice',
        ]);

        $invoice = StripeWebhookHelper::invoicePaymentSucceeded([
            'id' => 'in_test_recorded_1',
            'customer' => 'cus_test_invoice',
            'amount_paid' => 1499,
            'currency' => 'usd',
            'hosted_invoice_url' => 'https://invoice.stripe.com/p/test1',
            'created' => 1_700_000_000,
        ]);

        app(HandleInvoicePaidAction::class)->execute($invoice);

        $this->assertDatabaseHas('subscription_payments', [
            'user_id' => $user->id,
            'amount' => 14.99,
            'currency' => 'USD',
            'status' => 'paid',
            'gateway' => 'stripe',
            'gateway_payment_id' => 'in_test_recorded_1',
            'invoice_url' => 'https://invoice.stripe.com/p/test1',
        ]);

        $payment = SubscriptionPayment::where('gateway_payment_id', 'in_test_recorded_1')->first();
        $this->assertNotNull($payment);
        $this->assertEquals(1_700_000_000, $payment->paid_at->timestamp);
    }

    public function test_invoice_paid_is_idempotent_for_duplicate_invoice(): void
    {
        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_test_dup',
        ]);

        $invoice = StripeWebhookHelper::invoicePaymentSucceeded([
            'id' => 'in_test_dup',
            'customer' => 'cus_test_dup',
            'amount_paid' => 999,
            'currency' => 'usd',
        ]);

        $action = app(HandleInvoicePaidAction::class);
        $action->execute($invoice);
        $action->execute($invoice);

        $this->assertEquals(
            1,
            SubscriptionPayment::where('gateway_payment_id', 'in_test_dup')->count(),
        );
    }

    public function test_invoice_paid_ignores_invoice_without_customer(): void
    {
        $invoice = StripeWebhookHelper::invoicePaymentSucceeded([
            'customer' => null,
        ]);

        app(HandleInvoicePaidAction::class)->execute($invoice);

        $this->assertDatabaseCount('subscription_payments', 0);
    }

    public function test_invoice_paid_ignores_zero_amount_invoice(): void
    {
        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_test_zero',
        ]);

        $invoice = StripeWebhookHelper::invoicePaymentSucceeded([
            'customer' => 'cus_test_zero',
            'amount_paid' => 0,
        ]);

        app(HandleInvoicePaidAction::class)->execute($invoice);

        $this->assertDatabaseCount('subscription_payments', 0);
    }

    public function test_invoice_paid_ignores_unknown_customer(): void
    {
        $invoice = StripeWebhookHelper::invoicePaymentSucceeded([
            'customer' => 'cus_test_nobody',
            'amount_paid' => 500,
        ]);

        app(HandleInvoicePaidAction::class)->execute($invoice);

        $this->assertDatabaseCount('subscription_payments', 0);
    }
}
