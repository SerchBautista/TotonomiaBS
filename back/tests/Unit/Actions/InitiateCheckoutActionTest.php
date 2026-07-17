<?php

namespace Tests\Unit\Actions;

use App\Actions\InitiateCheckoutAction;
use App\Contracts\AssignUserPlanActionInterface;
use App\Contracts\PaymentGatewayContract;
use App\Exceptions\DomainConflictException;
use App\Models\User;
use App\ValueObjects\CheckoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InitiateCheckoutActionTest extends TestCase
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

    private function makeGatewayMock(CheckoutSession $session): PaymentGatewayContract
    {
        $mock = Mockery::mock(PaymentGatewayContract::class);
        $mock->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn($session);

        return $mock;
    }

    private function makePlanMock(): AssignUserPlanActionInterface
    {
        $mock = Mockery::mock(AssignUserPlanActionInterface::class);

        return $mock;
    }

    public function test_returns_checkout_url_from_gateway_for_free_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $session = new CheckoutSession(
            url: 'https://checkout.stripe.com/c/pay/cs_test_abc',
            isDummy: false,
        );

        $planMock = $this->makePlanMock();
        $planMock->shouldNotReceive('execute');

        $action = new InitiateCheckoutAction(
            $this->makeGatewayMock($session),
            $planMock,
        );

        $result = $action->execute($user);

        $this->assertSame($session, $result);
        $this->assertEquals('https://checkout.stripe.com/c/pay/cs_test_abc', $result->url);
        $this->assertFalse($result->isDummy);
    }

    public function test_throws_domain_conflict_for_premium_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $user->assignRole('premium');

        $planMock = $this->makePlanMock();
        $planMock->shouldNotReceive('execute');

        // The gateway must NOT be called when the user already has premium.
        $gateway = Mockery::mock(PaymentGatewayContract::class);
        $gateway->shouldNotReceive('createCheckoutSession');

        $action = new InitiateCheckoutAction($gateway, $planMock);

        $this->expectException(DomainConflictException::class);

        try {
            $action->execute($user);
        } catch (DomainConflictException $e) {
            $this->assertEquals('subscription_already_active', $e->errorCode());
            throw $e;
        }
    }

    public function test_dummy_session_assigns_premium_and_creates_zero_payment(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $session = new CheckoutSession(
            url: '/pricing/success?dummy=true',
            isDummy: true,
        );

        $planMock = Mockery::mock(AssignUserPlanActionInterface::class);
        $planMock->shouldReceive('execute')
            ->once()
            ->with(Mockery::on(fn (User $u) => $u->id === $user->id), 'premium');

        $action = new InitiateCheckoutAction(
            $this->makeGatewayMock($session),
            $planMock,
        );

        $result = $action->execute($user);

        $this->assertTrue($result->isDummy);
        $this->assertDatabaseHas('subscription_payments', [
            'user_id' => $user->id,
            'amount' => 0.00,
            'currency' => 'USD',
            'status' => 'paid',
            'gateway' => 'dummy',
        ]);
    }

    public function test_real_gateway_session_does_not_assign_plan_nor_create_payment(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $session = new CheckoutSession(
            url: 'https://checkout.stripe.com/c/pay/cs_test_real',
            isDummy: false,
        );

        $planMock = $this->makePlanMock();
        $planMock->shouldNotReceive('execute');

        $action = new InitiateCheckoutAction(
            $this->makeGatewayMock($session),
            $planMock,
        );

        $action->execute($user);

        $this->assertFalse($user->fresh()->hasRole('premium'));
        $this->assertDatabaseCount('subscription_payments', 0);
    }

    public function test_resolves_real_implementation_from_container(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        // Bind a real DummyGatewayService so we exercise the container
        // resolution path used by the controller.
        $this->app->instance(
            PaymentGatewayContract::class,
            new \App\Services\DummyGatewayService,
        );

        $result = app(InitiateCheckoutAction::class)->execute($user);

        $this->assertTrue($result->isDummy);
        $this->assertTrue($user->fresh()->hasRole('premium'));
        $this->assertDatabaseHas('subscription_payments', [
            'user_id' => $user->id,
            'gateway' => 'dummy',
        ]);
    }
}
