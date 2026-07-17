<?php

namespace Tests\Feature;

use App\Models\OtherPaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class OtherPaymentMethodDefaultTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'premium', 'guard_name' => 'api']);
    }

    public function test_set_default_activates_method_and_deactivates_previous(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();

        $previous = OtherPaymentMethod::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'is_default' => true]);
        $previous->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);
        $method = OtherPaymentMethod::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'is_default' => false]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        $this->actingAsUser($owner);

        $response = $this->patchJson("/api/v1/workspaces/{$workspace->id}/other-payment-methods/{$method->id}/default");

        $response->assertOk()
            ->assertJsonPath('data.id', $method->id)
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('other_payment_methods', ['id' => $method->id, 'is_default' => true]);
        $this->assertDatabaseHas('other_payment_methods', ['id' => $previous->id, 'is_default' => false]);
    }

    public function test_toggle_on_already_default_method_unmarks_it(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $method = OtherPaymentMethod::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id, 'is_default' => true]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        $this->actingAsUser($owner);

        $response = $this->patchJson("/api/v1/workspaces/{$workspace->id}/other-payment-methods/{$method->id}/default");

        $response->assertOk()
            ->assertJsonPath('data.is_default', false);

        $this->assertDatabaseHas('other_payment_methods', ['id' => $method->id, 'is_default' => false]);
    }

    public function test_non_owner_member_receives_403(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $method = OtherPaymentMethod::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $owner->id]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        $member = User::factory()->create();
        $member->assignRole('user');
        $workspace->members()->attach($member->id, ['role' => 'viewer']);

        $this->actingAsUser($member);

        $response = $this->patchJson("/api/v1/workspaces/{$workspace->id}/other-payment-methods/{$method->id}/default");

        $response->assertForbidden();
    }
}
