<?php

namespace Tests\Feature;

use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class SubscriptionStatusTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'premium', 'guard_name' => 'api']);
    }

    public function test_authenticated_user_gets_subscription_status(): void
    {
        $user = User::factory()->create([
            'subscription_ends_at' => now()->addMonth(),
        ]);
        $user->assignRole('user');
        $user->assignRole('premium');

        $paidAt = now()->subDays(3);
        SubscriptionPayment::create([
            'user_id' => $user->id,
            'amount' => 9.99,
            'currency' => 'USD',
            'status' => 'paid',
            'gateway' => 'stripe',
            'gateway_payment_id' => 'in_test_status_123',
            'invoice_url' => 'https://stripe.com/invoice/test',
            'paid_at' => $paidAt,
        ]);

        $this->actingAsUser($user);

        $response = $this->getJson('/api/v1/user/subscription');

        $response->assertOk()
            ->assertJsonPath('plan', 'premium')
            ->assertJsonPath('subscription_ends_at', $user->subscription_ends_at->toIso8601String())
            ->assertJsonCount(1, 'payments')
            ->assertJsonPath('payments.0.date', $paidAt->toDateString())
            ->assertJsonPath('payments.0.amount', 9.99)
            ->assertJsonPath('payments.0.currency', 'USD')
            ->assertJsonPath('payments.0.status', 'paid')
            ->assertJsonPath('payments.0.gateway', 'stripe')
            ->assertJsonPath('payments.0.invoice_url', 'https://stripe.com/invoice/test');
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->withHeader('X-Request-Id', 'req-subscription-status-401')
            ->getJson('/api/v1/user/subscription');

        $response->assertUnauthorized()
            ->assertExactJson([
                'status' => 401,
                'code' => 'unauthenticated',
                'message' => 'Authentication is required to access this resource.',
                'request_id' => 'req-subscription-status-401',
            ]);
    }
}
