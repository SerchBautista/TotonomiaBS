<?php

namespace Tests\Unit\Actions;

use App\Actions\HandleCheckoutCompletedAction;
use App\Contracts\StripeSubscriptionServiceInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Helpers\StripeWebhookHelper;
use Tests\TestCase;

class HandleCheckoutCompletedActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'premium', 'guard_name' => 'api']);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_checkout_completed_upgrades_user_to_premium(): void
    {
        $user = User::factory()->create([
            'email' => 'buyer@example.test',
        ]);
        $user->assignRole('user');

        $periodEnd = time() + 60 * 60 * 24 * 30;

        $stripeSubscriptions = Mockery::mock(StripeSubscriptionServiceInterface::class);
        $stripeSubscriptions->shouldReceive('retrieveSubscription')
            ->once()
            ->with('sub_test_abc')
            ->andReturn((object) ['current_period_end' => $periodEnd]);

        $session = StripeWebhookHelper::checkoutSessionCompleted([
            'customer' => 'cus_test_xyz',
            'customer_email' => $user->email,
            'customer_details' => ['email' => $user->email],
            'subscription' => 'sub_test_abc',
        ]);

        app(HandleCheckoutCompletedAction::class, [
            'stripeSubscriptions' => $stripeSubscriptions,
        ])->execute($session);

        $fresh = $user->fresh();
        $this->assertEquals('cus_test_xyz', $fresh->stripe_customer_id);
        $this->assertNotNull($fresh->subscription_ends_at);
        $this->assertTrue($fresh->hasRole('premium'));
        $this->assertTrue($fresh->hasRole('user'));
    }

    public function test_checkout_completed_ignores_event_without_customer_email(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $session = StripeWebhookHelper::checkoutSessionCompleted([
            'customer_email' => null,
            'customer_details' => null,
        ]);

        $stripeSubscriptions = Mockery::mock(StripeSubscriptionServiceInterface::class);
        $stripeSubscriptions->shouldNotReceive('retrieveSubscription');

        app(HandleCheckoutCompletedAction::class, [
            'stripeSubscriptions' => $stripeSubscriptions,
        ])->execute($session);

        $this->assertNull($user->fresh()->stripe_customer_id);
        $this->assertFalse($user->fresh()->hasRole('premium'));
    }

    public function test_checkout_completed_ignores_event_for_unknown_user(): void
    {
        $session = StripeWebhookHelper::checkoutSessionCompleted([
            'customer_email' => 'ghost@example.test',
        ]);

        $stripeSubscriptions = Mockery::mock(StripeSubscriptionServiceInterface::class);
        $stripeSubscriptions->shouldNotReceive('retrieveSubscription');

        app(HandleCheckoutCompletedAction::class, [
            'stripeSubscriptions' => $stripeSubscriptions,
        ])->execute($session);

        // No user matched, so the database has no subscription_payments and
        // no user got the premium role.
        $this->assertDatabaseCount('users', 0);
    }

    public function test_checkout_completed_handles_missing_subscription_metadata(): void
    {
        $user = User::factory()->create(['email' => 'no-sub@example.test']);
        $user->assignRole('user');

        $session = StripeWebhookHelper::checkoutSessionCompleted([
            'customer_email' => $user->email,
            'subscription' => null,
        ]);

        $stripeSubscriptions = Mockery::mock(StripeSubscriptionServiceInterface::class);
        $stripeSubscriptions->shouldNotReceive('retrieveSubscription');

        app(HandleCheckoutCompletedAction::class, [
            'stripeSubscriptions' => $stripeSubscriptions,
        ])->execute($session);

        $fresh = $user->fresh();
        $this->assertNull($fresh->subscription_ends_at);
        $this->assertTrue($fresh->hasRole('premium'));
    }

    public function test_checkout_completed_keeps_user_role_when_upgrading(): void
    {
        $user = User::factory()->create(['email' => 'keep-user-role@example.test']);
        $user->assignRole('user');

        $session = StripeWebhookHelper::checkoutSessionCompleted([
            'customer_email' => $user->email,
            'subscription' => null,
        ]);

        $stripeSubscriptions = Mockery::mock(StripeSubscriptionServiceInterface::class);

        app(HandleCheckoutCompletedAction::class, [
            'stripeSubscriptions' => $stripeSubscriptions,
        ])->execute($session);

        $fresh = $user->fresh();
        $this->assertTrue($fresh->hasRole('user'));
        $this->assertTrue($fresh->hasRole('premium'));
    }
}
