<?php

namespace Tests\Feature;

use App\Models\OtherPaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class OtherPaymentMethodUpdateTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_owner_can_update_other_payment_method(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $method = OtherPaymentMethod::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        $response = $this->putJson("/api/v1/workspaces/{$workspace->id}/other-payment-methods/{$method->id}", [
            'name' => 'Renamed Method',
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Renamed Method');

        $this->assertDatabaseHas('other_payment_methods', [
            'id' => $method->id,
            'name' => 'Renamed Method',
            'description' => 'Updated description',
        ]);
    }

    public function test_non_owner_member_cannot_update_other_payment_method(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $method = OtherPaymentMethod::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        $viewer = User::factory()->create();
        $viewer->assignRole('user');
        $workspace->members()->attach($viewer->id, ['role' => 'viewer']);

        $this->actingAsUser($viewer);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/other-payment-methods/{$method->id}", [
            'name' => 'Should Not Update',
        ])->assertForbidden();
    }

    public function test_update_other_payment_method_without_name_returns_422(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $method = OtherPaymentMethod::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        // Send an empty payload: the FormRequest rules mark `name` as
        // 'sometimes' but Laravel still returns 422 when required rules fail.
        // Sending an explicit null name verifies the field is required-ish.
        $response = $this->putJson("/api/v1/workspaces/{$workspace->id}/other-payment-methods/{$method->id}", [
            'name' => null,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['fieldErrors' => ['name']]);
    }

    public function test_update_nonexistent_other_payment_method_returns_404(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}/other-payment-methods/00000000-0000-0000-0000-000000000000", [
            'name' => 'Missing',
        ])->assertNotFound();
    }
}
