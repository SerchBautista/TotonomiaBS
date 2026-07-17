<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_resource_returns_expected_top_level_fields(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->forUser($user)->create([
            'name' => 'Comida',
            'icon' => 'utensils',
            'color' => '#FF6B6B',
        ]);

        $resource = new CategoryResource($category);
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        $this->assertSame($category->id, $data['id']);
        $this->assertSame($user->id, $data['user_id']);
        $this->assertSame('Comida', $data['name']);
        $this->assertSame('utensils', $data['icon']);
        $this->assertSame('#FF6B6B', $data['color']);
        $this->assertFalse($data['is_default']);
        $this->assertArrayHasKey('created_at', $data);
    }

    public function test_resource_is_default_is_boolean(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->forUser($user)->create(['is_default' => true]);

        $resource = new CategoryResource($category);
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        $this->assertTrue($data['is_default']);
        $this->assertIsBool($data['is_default']);
    }

    public function test_resource_omits_conditional_workspace_fields_when_attributes_not_set(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->forUser($user)->create();

        $resource = new CategoryResource($category);
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        // The resource uses array_key_exists on $this->resource->getAttributes()
        // to decide whether to include these. A freshly built model has none
        // of these extras set.
        $this->assertArrayNotHasKey('is_linked', $data);
        $this->assertArrayNotHasKey('is_active_in_workspace', $data);
        $this->assertArrayNotHasKey('is_in_use_in_workspace', $data);
        $this->assertArrayNotHasKey('is_valid_for_transactions', $data);
        $this->assertArrayNotHasKey('state', $data);
        $this->assertArrayNotHasKey('linked_workspaces_count', $data);
        $this->assertArrayNotHasKey('linked_workspaces', $data);
    }

    public function test_resource_includes_is_linked_when_attribute_is_set(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->forUser($user)->create();
        // Set the attribute on the model instance — the resource checks
        // array_key_exists() on $model->getAttributes() to decide inclusion.
        $category->setAttribute('is_linked', true);

        $resource = new CategoryResource($category);
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        $this->assertArrayHasKey('is_linked', $data);
        $this->assertTrue($data['is_linked']);
    }

    public function test_resource_includes_state_when_attribute_is_set(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->forUser($user)->create();
        $category->setAttribute('state', 'shared');

        $resource = new CategoryResource($category);
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        $this->assertSame('shared', $data['state']);
    }

    public function test_resource_includes_linked_workspaces_count_when_attribute_is_set(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->forUser($user)->create();
        $category->setAttribute('linked_workspaces_count', 3);

        $resource = new CategoryResource($category);
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        $this->assertSame(3, $data['linked_workspaces_count']);
    }

    public function test_resource_includes_linked_workspaces_when_attribute_is_set(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->forUser($user)->create();
        $linked = [
            ['id' => 'w-1', 'name' => 'Workspace 1', 'is_shared' => true, 'is_active' => true],
            ['id' => 'w-2', 'name' => 'Workspace 2', 'is_shared' => false, 'is_active' => true],
        ];
        $category->setAttribute('linked_workspaces', $linked);

        $resource = new CategoryResource($category);
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        $this->assertCount(2, $data['linked_workspaces']);
        $this->assertSame('Workspace 1', $data['linked_workspaces'][0]['name']);
        $this->assertTrue($data['linked_workspaces'][0]['is_shared']);
    }

    public function test_resource_includes_all_workspace_state_attributes_when_set_together(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->forUser($user)->create();

        // Simulate a category that has been loaded with workspace-context
        // attributes (typical of the CategoryIndex endpoint).
        $category->setAttribute('is_linked', true);
        $category->setAttribute('is_active_in_workspace', true);
        $category->setAttribute('is_in_use_in_workspace', false);
        $category->setAttribute('is_valid_for_transactions', true);
        $category->setAttribute('state', 'shared');
        $category->setAttribute('linked_workspaces_count', 2);

        $resource = new CategoryResource($category);
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        $this->assertTrue($data['is_linked']);
        $this->assertTrue($data['is_active_in_workspace']);
        $this->assertFalse($data['is_in_use_in_workspace']);
        $this->assertTrue($data['is_valid_for_transactions']);
        $this->assertSame('shared', $data['state']);
        $this->assertSame(2, $data['linked_workspaces_count']);
    }

    public function test_resource_category_id_matches_requested_id(): void
    {
        $user = User::factory()->create();
        $category1 = Category::factory()->forUser($user)->create(['name' => 'Alpha']);
        $category2 = Category::factory()->forUser($user)->create(['name' => 'Beta']);

        $response1 = (new CategoryResource($category1))->response(Request::create('/'));
        $response2 = (new CategoryResource($category2))->response(Request::create('/'));

        $this->assertSame($category1->id, $response1->getData(true)['data']['id']);
        $this->assertSame($category2->id, $response2->getData(true)['data']['id']);
        $this->assertSame('Alpha', $response1->getData(true)['data']['name']);
        $this->assertSame('Beta', $response2->getData(true)['data']['name']);
    }

    public function test_resource_works_when_category_is_linked_to_a_workspace(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $category = Category::factory()->forUser($user)->create();
        $category->workspaces()->attach($workspace->id, [
            'is_shared' => true,
            'is_active' => true,
        ]);

        $resource = new CategoryResource($category);
        $response = $resource->response(Request::create('/'));
        $data = $response->getData(true)['data'];

        // The basic fields are always present.
        $this->assertSame($category->id, $data['id']);
        $this->assertSame($category->name, $data['name']);

        // The pivot state is not surfaced unless `setAttribute()` was used
        // (e.g. the controller sets `is_linked` from the pivot before
        // serializing).
        $this->assertArrayNotHasKey('is_linked', $data);
    }
}
