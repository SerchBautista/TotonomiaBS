<?php

namespace Tests\Feature;

use App\Contracts\PaymentGatewayContract;
use App\Models\User;
use App\ValueObjects\CheckoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class SubscriptionCheckoutTest extends TestCase
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

    public function test_authenticated_user_receives_checkout_url(): void
    {
        $this->app->bind(PaymentGatewayContract::class, fn () => new class implements PaymentGatewayContract
        {
            public function createCheckoutSession(User $user): CheckoutSession
            {
                return new CheckoutSession(url: '/pricing/success?dummy=true', isDummy: true);
            }
        });

        $user = User::factory()->create();
        $user->assignRole('user');
        $this->actingAsUser($user);

        $response = $this->postJson('/api/v1/subscriptions/checkout');

        $response->assertOk()
            ->assertJsonStructure(['url', 'is_dummy'])
            ->assertJsonPath('is_dummy', true);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->withHeader('X-Request-Id', 'req-subscription-checkout-401')
            ->postJson('/api/v1/subscriptions/checkout');

        $response->assertUnauthorized()
            ->assertExactJson([
                'status' => 401,
                'code' => 'unauthenticated',
                'message' => 'Authentication is required to access this resource.',
                'request_id' => 'req-subscription-checkout-401',
            ]);
    }

    public function test_premium_user_receives_409(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $user->assignRole('premium');
        $this->actingAsUser($user);

        $response = $this->withHeader('X-Request-Id', 'req-subscription-checkout-conflict-409')
            ->postJson('/api/v1/subscriptions/checkout');

        $response->assertStatus(409)
            ->assertJsonPath('status', 409)
            ->assertJsonPath('code', 'subscription_already_active')
            ->assertJsonPath('message', __('api.errors.subscription_already_active'))
            ->assertJsonPath('request_id', 'req-subscription-checkout-conflict-409');
    }
}
