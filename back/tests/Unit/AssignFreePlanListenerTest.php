<?php

namespace Tests\Unit;

use App\Contracts\AssignUserPlanActionInterface;
use App\Events\UserRegistered;
use App\Listeners\AssignFreePlanListener;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignFreePlanListenerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'premium', 'guard_name' => 'api']);
    }

    public function test_listener_invokes_assign_free_plan_action_on_user_registration(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        // The user is freshly registered: no premium role yet.
        $this->assertFalse($user->hasRole('premium'));

        $action = $this->createMock(AssignUserPlanActionInterface::class);
        $action->expects($this->once())
            ->method('execute')
            ->with(
                $this->callback(function (User $captured) use ($user): bool {
                    return $captured->id === $user->id;
                }),
                'free',
            );

        $listener = new AssignFreePlanListener($action);
        $listener->handle(new UserRegistered($user));
    }

    public function test_listener_assigns_free_plan_via_real_action(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        // Resolve the real action from the container — the listener should
        // call it and grant the `premium` role removal (i.e. ensure the user
        // is on the free tier).
        $action = app(AssignUserPlanActionInterface::class);

        $listener = new AssignFreePlanListener($action);
        $listener->handle(new UserRegistered($user));

        $fresh = $user->fresh();
        $this->assertFalse($fresh->hasRole('premium'));
        $this->assertTrue($fresh->hasRole('user'));
    }

    public function test_listener_is_idempotent_when_invoked_twice(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        $action = app(AssignUserPlanActionInterface::class);

        $listener = new AssignFreePlanListener($action);

        // Triggering the listener twice in a row must be safe: the second
        // call is a no-op for the user (already on free), and the pivot must
        // not accumulate duplicate role rows.
        $listener->handle(new UserRegistered($user));
        $listener->handle(new UserRegistered($user));

        $fresh = $user->fresh();
        $this->assertFalse($fresh->hasRole('premium'));
        $this->assertTrue($fresh->hasRole('user'));

        // Exactly one 'user' role in the pivot (no duplication from the
        // repeated free-plan assignment).
        $this->assertDatabaseCount('model_has_roles', 1);
    }

    public function test_listener_revokes_premium_when_user_already_has_it(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $user->assignRole('premium');

        $this->assertTrue($user->hasRole('premium'));

        $action = app(AssignUserPlanActionInterface::class);
        $listener = new AssignFreePlanListener($action);
        $listener->handle(new UserRegistered($user));

        $fresh = $user->fresh();
        $this->assertFalse($fresh->hasRole('premium'));
        $this->assertTrue($fresh->hasRole('user'));
    }
}
