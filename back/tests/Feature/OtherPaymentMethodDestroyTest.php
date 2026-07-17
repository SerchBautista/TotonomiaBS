<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\OtherPaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class OtherPaymentMethodDestroyTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_owner_can_delete_other_payment_method(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $method = OtherPaymentMethod::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/other-payment-methods/{$method->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('other_payment_methods', ['id' => $method->id]);
    }

    public function test_destroy_other_payment_method_with_linked_expenses_returns_409_other_payment_method_in_use(): void
    {
        // H-010 fix: deleting an OtherPaymentMethod that still has expenses
        // (or fixed expenses) linked must be rejected with 409
        // `other_payment_method_in_use` instead of silently soft-deleting it.
        ['user' => $user, 'workspace' => $workspace, 'category' => $category] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $method = OtherPaymentMethod::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'payment_type' => 'other',
            'payment_instrument_id' => $method->id,
            'amount' => '75.00',
        ]);

        $response = $this->deleteJson("/api/v1/workspaces/{$workspace->id}/other-payment-methods/{$method->id}");

        $response->assertStatus(409)
            ->assertJsonPath('status', 409)
            ->assertJsonPath('code', 'other_payment_method_in_use');

        $this->assertNotSoftDeleted('other_payment_methods', ['id' => $method->id]);
    }

    public function test_non_member_cannot_delete_other_payment_method(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $method = OtherPaymentMethod::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        $outsider = User::factory()->create();
        $outsider->assignRole('user');
        $this->actingAsUser($outsider);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/other-payment-methods/{$method->id}")
            ->assertForbidden();
    }
}
