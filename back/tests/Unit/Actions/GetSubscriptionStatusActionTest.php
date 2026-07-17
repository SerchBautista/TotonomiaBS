<?php

namespace Tests\Unit\Actions;

use App\Actions\GetSubscriptionStatusAction;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class GetSubscriptionStatusActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'premium', 'guard_name' => 'api']);
    }

    private function makeUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return $user;
    }

    public function test_returns_free_when_user_has_no_premium_role(): void
    {
        $user = $this->makeUser();

        $status = app(GetSubscriptionStatusAction::class)->execute($user);

        $this->assertEquals('free', $status->plan);
        $this->assertNull($status->subscriptionEndsAt);
        $this->assertSame([], $status->payments);
    }

    public function test_returns_premium_when_premium_role_and_active_subscription(): void
    {
        $user = $this->makeUser();
        $user->assignRole('premium');
        $endsAt = now()->addDays(15);
        $user->update(['subscription_ends_at' => $endsAt]);

        $status = app(GetSubscriptionStatusAction::class)->execute($user);

        $this->assertEquals('premium', $status->plan);
        $this->assertNotNull($status->subscriptionEndsAt);
        $this->assertEquals(
            $endsAt->toIso8601String(),
            $status->subscriptionEndsAt->toIso8601String(),
        );
    }

    public function test_returns_free_when_subscription_ends_at_is_null(): void
    {
        $user = $this->makeUser();
        $user->assignRole('premium');
        $user->update(['subscription_ends_at' => null]);

        $status = app(GetSubscriptionStatusAction::class)->execute($user);

        // A user with the premium role but no subscription_ends_at
        // (e.g. cancelled before any charge) is reported as "free":
        // the plan reflects the effective subscription state, not
        // just the assigned role.
        $this->assertEquals('free', $status->plan);
        $this->assertNull($status->subscriptionEndsAt);
    }

    public function test_returns_free_when_subscription_ends_at_is_past(): void
    {
        $user = $this->makeUser();
        $user->assignRole('premium');
        $user->update(['subscription_ends_at' => now()->subDay()]);

        $status = app(GetSubscriptionStatusAction::class)->execute($user);

        // A user whose subscription_ends_at is in the past is no
        // longer an active subscriber, so plan must be "free" even
        // though the premium role is still assigned.
        $this->assertEquals('free', $status->plan);
        $this->assertNotNull($status->subscriptionEndsAt);
    }

    public function test_returns_free_when_user_has_no_premium_role_even_if_subscription_ends_at_is_future(): void
    {
        $user = $this->makeUser();
        $user->update(['subscription_ends_at' => now()->addDays(30)]);

        $status = app(GetSubscriptionStatusAction::class)->execute($user);

        // Edge case: a future subscription_ends_at without the
        // premium role does not grant "premium" plan — both
        // conditions (role AND active subscription) are required.
        $this->assertEquals('free', $status->plan);
        $this->assertNotNull($status->subscriptionEndsAt);
    }

    public function test_returns_recent_payments_for_user(): void
    {
        $user = $this->makeUser();

        $older = SubscriptionPayment::create([
            'user_id' => $user->id,
            'amount' => 9.99,
            'currency' => 'USD',
            'status' => 'paid',
            'gateway' => 'stripe',
            'gateway_payment_id' => 'in_test_recent_2',
            'invoice_url' => 'https://stripe.com/invoice/recent2',
            'paid_at' => now()->subDays(7),
        ]);

        $newer = SubscriptionPayment::create([
            'user_id' => $user->id,
            'amount' => 19.99,
            'currency' => 'USD',
            'status' => 'paid',
            'gateway' => 'stripe',
            'gateway_payment_id' => 'in_test_recent_1',
            'invoice_url' => 'https://stripe.com/invoice/recent1',
            'paid_at' => now()->subDay(),
        ]);

        $status = app(GetSubscriptionStatusAction::class)->execute($user);

        $this->assertCount(2, $status->payments);
        // Order: subscriptionPayments() relation orders by paid_at desc.
        $this->assertEquals(19.99, $status->payments[0]->amount);
        $this->assertEquals('paid', $status->payments[0]->status);
        $this->assertEquals($newer->paid_at->toDateString(), $status->payments[0]->date);
        $this->assertEquals(9.99, $status->payments[1]->amount);
        $this->assertEquals($older->paid_at->toDateString(), $status->payments[1]->date);
        $this->assertEquals('stripe', $status->payments[0]->gateway);
        $this->assertEquals('https://stripe.com/invoice/recent1', $status->payments[0]->invoiceUrl);
    }

    public function test_limits_payments_to_24(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 30; $i++) {
            SubscriptionPayment::create([
                'user_id' => $user->id,
                'amount' => 1.00,
                'currency' => 'USD',
                'status' => 'paid',
                'gateway' => 'stripe',
                'gateway_payment_id' => 'in_test_limit_'.$i,
                'paid_at' => now()->subDays($i),
            ]);
        }

        $status = app(GetSubscriptionStatusAction::class)->execute($user);

        $this->assertCount(24, $status->payments);
    }

    public function test_does_not_leak_payments_from_other_users(): void
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();

        SubscriptionPayment::create([
            'user_id' => $userB->id,
            'amount' => 5.00,
            'currency' => 'USD',
            'status' => 'paid',
            'gateway' => 'stripe',
            'gateway_payment_id' => 'in_test_other_user',
            'paid_at' => now(),
        ]);

        $status = app(GetSubscriptionStatusAction::class)->execute($userA);

        $this->assertCount(0, $status->payments);
    }
}
