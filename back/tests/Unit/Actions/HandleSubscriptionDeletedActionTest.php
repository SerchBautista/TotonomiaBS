<?php

namespace Tests\Unit\Actions;

use App\Actions\HandleSubscriptionDeletedAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Helpers\StripeWebhookHelper;
use Tests\TestCase;

class HandleSubscriptionDeletedActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'premium', 'guard_name' => 'api']);
    }

    public function test_subscription_deleted_revokes_premium_role_and_ends_subscription(): void
    {
        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_test_del',
            'subscription_ends_at' => now()->addDays(15),
        ]);
        $user->assignRole('user');
        $user->assignRole('premium');

        $subscription = StripeWebhookHelper::customerSubscriptionDeleted([
            'customer' => 'cus_test_del',
            'status' => 'canceled',
        ]);

        app(HandleSubscriptionDeletedAction::class)->execute($subscription);

        $fresh = $user->fresh();
        $this->assertNull($fresh->subscription_ends_at);
        $this->assertTrue($fresh->hasRole('user'));
        $this->assertFalse($fresh->hasRole('premium'));
    }

    public function test_subscription_deleted_for_unknown_customer_is_noop(): void
    {
        $subscription = StripeWebhookHelper::customerSubscriptionDeleted([
            'customer' => 'cus_test_ghost',
        ]);

        // Should not raise; no user exists to mutate.
        app(HandleSubscriptionDeletedAction::class)->execute($subscription);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_subscription_deleted_without_customer_id_is_noop(): void
    {
        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_test_real',
            'subscription_ends_at' => now()->addDays(15),
        ]);
        $user->assignRole('premium');

        $subscription = StripeWebhookHelper::customerSubscriptionDeleted([
            'customer' => null,
        ]);

        app(HandleSubscriptionDeletedAction::class)->execute($subscription);

        $fresh = $user->fresh();
        // User must remain untouched.
        $this->assertNotNull($fresh->subscription_ends_at);
        $this->assertTrue($fresh->hasRole('premium'));
    }
}
