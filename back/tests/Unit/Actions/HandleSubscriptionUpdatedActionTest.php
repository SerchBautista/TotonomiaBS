<?php

namespace Tests\Unit\Actions;

use App\Actions\HandleSubscriptionUpdatedAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Helpers\StripeWebhookHelper;
use Tests\TestCase;

class HandleSubscriptionUpdatedActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'premium', 'guard_name' => 'api']);
    }

    public function test_subscription_updated_refreshes_period_end_for_active(): void
    {
        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_test_active',
            'subscription_ends_at' => now()->subDay(),
        ]);
        $user->assignRole('premium');

        $newPeriodEnd = now()->addDays(30)->timestamp;

        $subscription = StripeWebhookHelper::customerSubscriptionUpdated([
            'customer' => 'cus_test_active',
            'status' => 'active',
            'current_period_end' => $newPeriodEnd,
        ]);

        app(HandleSubscriptionUpdatedAction::class)->execute($subscription);

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->subscription_ends_at);
        $this->assertEquals(
            $newPeriodEnd,
            $fresh->subscription_ends_at->timestamp,
        );
        $this->assertTrue($fresh->hasRole('premium'));
    }

    public function test_subscription_updated_for_past_due_keeps_period_end(): void
    {
        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_test_past_due',
        ]);

        $newPeriodEnd = now()->addDays(15)->timestamp;

        $subscription = StripeWebhookHelper::customerSubscriptionUpdated([
            'customer' => 'cus_test_past_due',
            'status' => 'past_due',
            'current_period_end' => $newPeriodEnd,
        ]);

        app(HandleSubscriptionUpdatedAction::class)->execute($subscription);

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->subscription_ends_at);
        $this->assertEquals($newPeriodEnd, $fresh->subscription_ends_at->timestamp);
    }

    public function test_subscription_updated_ignores_missing_customer(): void
    {
        $user = User::factory()->create();
        $user->assignRole('premium');

        $subscription = StripeWebhookHelper::customerSubscriptionUpdated([
            'customer' => null,
        ]);

        app(HandleSubscriptionUpdatedAction::class)->execute($subscription);

        $this->assertNull($user->fresh()->subscription_ends_at);
    }

    public function test_subscription_updated_ignores_unknown_customer(): void
    {
        $subscription = StripeWebhookHelper::customerSubscriptionUpdated([
            'customer' => 'cus_test_nonexistent',
        ]);

        // Should be a no-op without raising.
        app(HandleSubscriptionUpdatedAction::class)->execute($subscription);

        $this->assertDatabaseCount('users', 0);
    }
}
