<?php

namespace Tests\Feature\Resources;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_resource_returns_admin_role_when_user_has_both_admin_and_user_roles(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->assignRole(['user', 'admin']);

        $resource = new UserResource($user);
        $array = $resource->toArray(request());

        $this->assertSame('admin', $array['role']);
    }

    public function test_user_resource_returns_admin_role_when_user_has_only_admin_role(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->assignRole('admin');

        $resource = new UserResource($user);
        $array = $resource->toArray(request());

        $this->assertSame('admin', $array['role']);
    }

    public function test_user_resource_returns_user_role_when_user_has_only_user_role(): void
    {
        $this->seed();

        $user = User::factory()->create();
        $user->assignRole('user');

        $resource = new UserResource($user);
        $array = $resource->toArray(request());

        $this->assertSame('user', $array['role']);
    }

    public function test_user_resource_returns_user_fallback_when_user_has_no_role(): void
    {
        $this->seed();

        $user = User::factory()->create();

        $resource = new UserResource($user);
        $array = $resource->toArray(request());

        $this->assertSame('user', $array['role']);
    }
}
