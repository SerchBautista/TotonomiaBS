<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\WorkspaceResource;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class WorkspaceResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'premium', 'guard_name' => 'api']);
    }

    public function test_resource_returns_expected_top_level_fields(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');
        $workspace = Workspace::factory()->create([
            'owner_id' => $owner->id,
            'name' => 'Mi equipo',
            'type' => 'familiar',
            'currency_code' => 'MXN',
        ]);

        $resource = new WorkspaceResource($workspace);
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        $this->assertSame($workspace->id, $data['id']);
        $this->assertSame($owner->id, $data['owner_id']);
        $this->assertSame('Mi equipo', $data['name']);
        $this->assertSame('familiar', $data['type']);
        $this->assertSame('MXN', $data['currency_code']);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);
    }

    public function test_resource_omits_owner_when_relation_not_loaded(): void
    {
        $workspace = Workspace::factory()->create();

        $resource = new WorkspaceResource($workspace);
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        $this->assertArrayNotHasKey('owner', $data);
        $this->assertArrayNotHasKey('owner_plan', $data);
    }

    public function test_resource_includes_owner_when_relation_loaded(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->load('owner');

        $resource = new WorkspaceResource($workspace);
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        $this->assertSame($owner->id, $data['owner']['id']);
    }

    public function test_resource_reports_owner_plan_as_premium_when_owner_has_premium_role(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('premium');
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->load('owner');

        $resource = new WorkspaceResource($workspace);
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        $this->assertSame('premium', $data['owner_plan']);
    }

    public function test_resource_reports_owner_plan_as_free_when_owner_has_no_premium_role(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->load('owner');

        $resource = new WorkspaceResource($workspace);
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        $this->assertSame('free', $data['owner_plan']);
    }

    public function test_resource_includes_members_count_when_counted(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        $member = User::factory()->create();
        $workspace->members()->attach($member->id, ['role' => 'member']);

        $workspace->loadCount('members');

        $resource = new WorkspaceResource($workspace);
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        $this->assertSame(2, $data['members_count']);
    }

    public function test_resource_omits_members_count_when_not_counted(): void
    {
        $workspace = Workspace::factory()->create();

        $resource = new WorkspaceResource($workspace);
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        $this->assertArrayNotHasKey('members_count', $data);
    }

    public function test_resource_resolves_current_user_permissions_for_owner(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        $request = Request::create('/');
        $request->setUserResolver(fn () => $owner);

        $resource = new WorkspaceResource($workspace);
        $response = $resource->response($request);
        $data = $response->getData(true)['data'];

        // Owner always has full permissions.
        $this->assertTrue($data['current_user_permissions']['can_add_fixed_expenses']);
        $this->assertTrue($data['current_user_permissions']['can_add_categories']);
    }

    public function test_resource_resolves_current_user_permissions_for_member(): void
    {
        $owner = User::factory()->create();
        $owner->assignRole('user');
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $workspace->members()->attach($owner->id, ['role' => 'owner']);

        $member = User::factory()->create();
        $workspace->members()->attach($member->id, [
            'role' => 'member',
            'can_add_fixed_expenses' => true,
            'can_add_categories' => false,
        ]);

        $request = Request::create('/');
        $request->setUserResolver(fn () => $member);

        $resource = new WorkspaceResource($workspace);
        $response = $resource->response($request);
        $data = $response->getData(true)['data'];

        $this->assertTrue($data['current_user_permissions']['can_add_fixed_expenses']);
        $this->assertFalse($data['current_user_permissions']['can_add_categories']);
    }

    public function test_resource_resolves_current_user_permissions_for_non_member(): void
    {
        $workspace = Workspace::factory()->create();
        $outsider = User::factory()->create();

        $request = Request::create('/');
        $request->setUserResolver(fn () => $outsider);

        $resource = new WorkspaceResource($workspace);
        $response = $resource->response($request);
        $data = $response->getData(true)['data'];

        $this->assertFalse($data['current_user_permissions']['can_add_fixed_expenses']);
        $this->assertFalse($data['current_user_permissions']['can_add_categories']);
    }

    public function test_resource_returns_default_permissions_when_no_user_is_authenticated(): void
    {
        $workspace = Workspace::factory()->create();

        $request = Request::create('/');
        // No setUserResolver: $request->user() returns null.

        $resource = new WorkspaceResource($workspace);
        $response = $resource->response($request);
        $data = $response->getData(true)['data'];

        $this->assertFalse($data['current_user_permissions']['can_add_fixed_expenses']);
        $this->assertFalse($data['current_user_permissions']['can_add_categories']);
    }
}
