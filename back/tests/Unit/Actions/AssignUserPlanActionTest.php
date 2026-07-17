<?php

namespace Tests\Unit\Actions;

use App\Contracts\AssignUserPlanActionInterface;
use App\Events\UserPlanChanged;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AssignUserPlanActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        Role::firstOrCreate(['name' => 'premium', 'guard_name' => 'api']);
    }

    private function action(): AssignUserPlanActionInterface
    {
        return app(AssignUserPlanActionInterface::class);
    }

    public function test_assigning_premium_grants_role_and_dispatches_event(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $user->assignRole('user');
        $this->assertFalse($user->hasRole('premium'));

        $this->action()->execute($user, 'premium');

        $fresh = $user->fresh();
        $this->assertTrue($fresh->hasRole('premium'));
        $this->assertTrue($fresh->hasRole('user'));

        Event::assertDispatched(UserPlanChanged::class, function (UserPlanChanged $event) use ($user): bool {
            return $event->user->id === $user->id && $event->plan === 'premium';
        });
    }

    public function test_revoking_premium_removes_role_and_dispatches_event(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $user->assignRole('user');
        $user->assignRole('premium');
        $this->assertTrue($user->hasRole('premium'));

        $this->action()->execute($user, 'free');

        $fresh = $user->fresh();
        $this->assertFalse($fresh->hasRole('premium'));
        $this->assertTrue($fresh->hasRole('user'));

        Event::assertDispatched(UserPlanChanged::class, function (UserPlanChanged $event) use ($user): bool {
            return $event->user->id === $user->id && $event->plan === 'free';
        });
    }

    public function test_assigning_premium_twice_is_idempotent_and_does_not_duplicate_role(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $user->assignRole('user');

        $this->action()->execute($user, 'premium');
        $this->action()->execute($user, 'premium');

        $fresh = $user->fresh();
        $this->assertTrue($fresh->hasRole('premium'));

        // The role must appear exactly once in the pivot table.
        $this->assertDatabaseCount('model_has_roles', 2); // 'user' + 'premium'

        // Both dispatches still happen (no de-duplication at the event level).
        Event::assertDispatchedTimes(UserPlanChanged::class, 2);
    }

    public function test_revoking_premium_from_user_without_premium_is_safe_noop(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $user->assignRole('user');
        $this->assertFalse($user->hasRole('premium'));

        // Spatie's removeRole is a no-op when the role is missing, so the
        // action must complete without raising.
        $this->action()->execute($user, 'free');

        $fresh = $user->fresh();
        $this->assertFalse($fresh->hasRole('premium'));
        $this->assertTrue($fresh->hasRole('user'));

        Event::assertDispatched(UserPlanChanged::class, function (UserPlanChanged $event) use ($user): bool {
            return $event->user->id === $user->id && $event->plan === 'free';
        });
    }

    public function test_assigning_premium_without_subscription_sets_ends_at_to_one_month_from_now(): void
    {
        $user = User::factory()->create(['subscription_ends_at' => null]);
        $user->assignRole('user');

        $this->action()->execute($user, 'premium');

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->subscription_ends_at);
        $this->assertEquals(
            Carbon::now()->addMonth()->toDateString(),
            $fresh->subscription_ends_at->toDateString(),
        );
    }

    public function test_assigning_premium_with_active_subscription_extends_by_one_month(): void
    {
        $originalEndsAt = Carbon::now()->addDays(10);
        $user = User::factory()->create(['subscription_ends_at' => $originalEndsAt]);
        $user->assignRole('user');

        $this->action()->execute($user, 'premium');

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->subscription_ends_at);
        $this->assertEquals(
            $originalEndsAt->copy()->addMonth()->toDateString(),
            $fresh->subscription_ends_at->toDateString(),
        );
    }

    public function test_assigning_premium_with_expired_subscription_sets_ends_at_to_one_month_from_now(): void
    {
        $expiredEndsAt = Carbon::now()->subDays(5);
        $user = User::factory()->create(['subscription_ends_at' => $expiredEndsAt]);
        $user->assignRole('user');

        $this->action()->execute($user, 'premium');

        $fresh = $user->fresh();
        $this->assertNotNull($fresh->subscription_ends_at);
        $this->assertEquals(
            Carbon::now()->addMonth()->toDateString(),
            $fresh->subscription_ends_at->toDateString(),
        );
    }

    public function test_assigning_free_clears_subscription_ends_at(): void
    {
        $user = User::factory()->create(['subscription_ends_at' => Carbon::now()->addMonth()]);
        $user->assignRole('user');
        $user->assignRole('premium');

        $this->action()->execute($user, 'free');

        $fresh = $user->fresh();
        $this->assertNull($fresh->subscription_ends_at);
        $this->assertFalse($fresh->hasRole('premium'));
    }
}
