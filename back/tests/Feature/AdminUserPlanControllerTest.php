<?php

namespace Tests\Feature;

use App\Events\UserPlanChanged;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;
use Tests\CreatesAdmin;
use Tests\TestCase;

class AdminUserPlanControllerTest extends TestCase
{
    use CreatesAdmin;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    public function test_unauthenticated_user_cannot_assign_plan(): void
    {
        $target = User::factory()->create();
        $target->assignRole('user');

        $this->postJson("/api/v1/admin/users/{$target->id}/plan", ['plan' => 'premium'])
            ->assertUnauthorized();

        $this->assertFalse($target->fresh()->hasRole('premium'));
    }

    public function test_regular_user_cannot_assign_plan(): void
    {
        $target = User::factory()->create();
        $target->assignRole('user');
        $regular = User::factory()->create();
        $regular->assignRole('user');

        Passport::actingAs($regular, ['*'], 'api');

        $this->postJson("/api/v1/admin/users/{$target->id}/plan", ['plan' => 'premium'])
            ->assertForbidden();

        $this->assertFalse($target->fresh()->hasRole('premium'));
    }

    public function test_admin_without_assign_plan_permission_is_forbidden(): void
    {
        // Admin role exists but the `users.assign-plan` permission is stripped
        // from the role so the `api.permission:users.assign-plan` middleware
        // must reject the request.
        Role::query()->where('name', 'admin')->where('guard_name', 'api')->first()
            ->revokePermissionTo('users.assign-plan');

        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        $target = User::factory()->create();
        $target->assignRole('user');

        $this->postJson("/api/v1/admin/users/{$target->id}/plan", ['plan' => 'premium'])
            ->assertForbidden()
            ->assertJsonStructure(['status', 'code', 'message']);

        $this->assertFalse($target->fresh()->hasRole('premium'));
    }

    public function test_admin_with_assign_plan_permission_can_assign_plan(): void
    {
        // Explicit positive-path coverage: the seeder already grants the
        // permission to the admin role, but we re-grant it here so the test
        // is robust to future seeder changes.
        Role::query()->where('name', 'admin')->where('guard_name', 'api')->first()
            ->givePermissionTo('users.assign-plan');

        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        $target = User::factory()->create();
        $target->assignRole('user');

        $this->postJson("/api/v1/admin/users/{$target->id}/plan", ['plan' => 'premium'])
            ->assertOk()
            ->assertJsonPath('data.plan', 'premium');

        $this->assertTrue($target->fresh()->hasRole('premium'));
    }

    public function test_admin_can_assign_premium_plan_to_user(): void
    {
        Event::fake();

        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        $target = User::factory()->create(['email' => 'target-user@example.com']);
        $target->assignRole('user');
        $this->assertFalse($target->hasRole('premium'));

        $response = $this->postJson("/api/v1/admin/users/{$target->id}/plan", [
            'plan' => 'premium',
        ])
            ->assertOk()
            ->assertJsonPath('data.plan', 'premium')
            ->assertJsonPath('data.id', $target->id);

        $fresh = $target->fresh();
        $this->assertTrue($fresh->hasRole('premium'));
        $this->assertTrue($fresh->hasRole('user'));

        Event::assertDispatched(UserPlanChanged::class, function (UserPlanChanged $event) use ($target): bool {
            return $event->user->id === $target->id && $event->plan === 'premium';
        });
    }

    public function test_admin_can_revoke_premium_plan_from_user(): void
    {
        Event::fake();

        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        $target = User::factory()->create();
        $target->assignRole('user');
        $target->assignRole('premium');
        $this->assertTrue($target->hasRole('premium'));

        $response = $this->postJson("/api/v1/admin/users/{$target->id}/plan", [
            'plan' => 'free',
        ])
            ->assertOk()
            ->assertJsonPath('data.plan', 'free');

        $fresh = $target->fresh();
        $this->assertFalse($fresh->hasRole('premium'));
        $this->assertTrue($fresh->hasRole('user'));

        Event::assertDispatched(UserPlanChanged::class, function (UserPlanChanged $event) use ($target): bool {
            return $event->user->id === $target->id && $event->plan === 'free';
        });
    }

    public function test_admin_revoke_premium_from_user_without_plan_is_noop(): void
    {
        Event::fake();

        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        $target = User::factory()->create();
        $target->assignRole('user');
        $this->assertFalse($target->hasRole('premium'));

        // Revoking when the user has no premium role must still succeed
        // because removeRole() is idempotent at the Spatie level.
        $this->postJson("/api/v1/admin/users/{$target->id}/plan", [
            'plan' => 'free',
        ])
            ->assertOk()
            ->assertJsonPath('data.plan', 'free');

        $this->assertFalse($target->fresh()->hasRole('premium'));
    }

    public function test_admin_assigning_plan_to_nonexistent_user_returns_404(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        // UUIDs are used as User primary keys, so a syntactically valid
        // UUID that does not match any row must trigger route model
        // binding failure → 404.
        $this->postJson('/api/v1/admin/users/00000000-0000-0000-0000-000000000000/plan', [
            'plan' => 'premium',
        ])->assertNotFound();
    }

    public function test_assigning_invalid_plan_value_returns_422(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        $target = User::factory()->create();
        $target->assignRole('user');

        $this->postJson("/api/v1/admin/users/{$target->id}/plan", [
            'plan' => 'enterprise',
        ])->assertUnprocessable();

        $this->assertFalse($target->fresh()->hasRole('premium'));
    }

    public function test_admin_assigning_premium_sets_subscription_ends_at_for_user_without_subscription(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        $target = User::factory()->create(['subscription_ends_at' => null]);
        $target->assignRole('user');

        $this->postJson("/api/v1/admin/users/{$target->id}/plan", [
            'plan' => 'premium',
        ])->assertOk();

        $fresh = $target->fresh();
        $this->assertTrue($fresh->hasRole('premium'));
        $this->assertNotNull($fresh->subscription_ends_at);
        $this->assertEquals(
            Carbon::now()->addMonth()->toDateString(),
            $fresh->subscription_ends_at->toDateString(),
        );
    }

    public function test_admin_assigning_premium_extends_active_subscription_by_one_month(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        $originalEndsAt = Carbon::now()->addDays(15);
        $target = User::factory()->create(['subscription_ends_at' => $originalEndsAt]);
        $target->assignRole('user');

        $this->postJson("/api/v1/admin/users/{$target->id}/plan", [
            'plan' => 'premium',
        ])->assertOk();

        $fresh = $target->fresh();
        $this->assertTrue($fresh->hasRole('premium'));
        $this->assertNotNull($fresh->subscription_ends_at);
        $this->assertEquals(
            $originalEndsAt->copy()->addMonth()->toDateString(),
            $fresh->subscription_ends_at->toDateString(),
        );
    }

    public function test_admin_assigning_free_clears_subscription_ends_at(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAsAdmin($admin);

        $target = User::factory()->create(['subscription_ends_at' => Carbon::now()->addMonth()]);
        $target->assignRole('user');
        $target->assignRole('premium');

        $this->postJson("/api/v1/admin/users/{$target->id}/plan", [
            'plan' => 'free',
        ])->assertOk();

        $fresh = $target->fresh();
        $this->assertFalse($fresh->hasRole('premium'));
        $this->assertNull($fresh->subscription_ends_at);
    }
}
