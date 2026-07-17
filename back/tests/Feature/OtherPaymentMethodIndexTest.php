<?php

namespace Tests\Feature;

use App\Models\OtherPaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class OtherPaymentMethodIndexTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_member_can_list_workspace_other_payment_methods(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        OtherPaymentMethod::factory()->count(2)->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ])->each(function (OtherPaymentMethod $method) use ($workspace): void {
            $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);
        });

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/other-payment-methods");

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'workspace_id', 'name', 'is_default']]])
            ->assertJsonCount(2, 'data');
    }

    public function test_non_member_cannot_list_workspace_other_payment_methods(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace();

        $outsider = User::factory()->create();
        $outsider->assignRole('user');
        $this->actingAsUser($outsider);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/other-payment-methods")
            ->assertForbidden();
    }
}
