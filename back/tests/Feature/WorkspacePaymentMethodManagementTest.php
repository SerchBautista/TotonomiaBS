<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\OtherPaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\CreatesWorkspace;
use Tests\TestCase;

use function collect;

class WorkspacePaymentMethodManagementTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_owner_can_manage_workspace_payment_methods_and_non_owner_gets_403(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'card' => $card] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $member = User::factory()->create();
        $member->assignRole('user');
        $workspace->members()->attach($member->id, ['role' => 'guest']);

        $this->actingAsUser($member);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/payment-methods")
            ->assertForbidden()
            ->assertJsonPath('status', 403)
            ->assertJsonPath('code', 'forbidden');

        $this->patchJson("/api/v1/workspaces/{$workspace->id}/payment-methods/{$card->id}/link", [
            'is_linked' => false,
        ])->assertForbidden();

        $this->postJson("/api/v1/workspaces/{$workspace->id}/payment-methods", [
            'type' => 'other',
            'name' => 'Transferencia',
        ])->assertForbidden();

        $this->actingAsUser($owner);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/payment-methods")
            ->assertOk();
    }

    public function test_non_member_cannot_access_workspace_valid_payment_methods_endpoint(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $outsider = User::factory()->create();
        $outsider->assignRole('user');

        $this->actingAsUser($outsider);

        $this->getJson("/api/v1/workspaces/{$workspace->id}/payment-methods/valid")
            ->assertForbidden()
            ->assertJsonPath('status', 403)
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_create_from_workspace_creates_and_links_payment_method(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $this->actingAsUser($owner);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/payment-methods", [
            'type' => 'card',
            'name' => 'Visa Compartida',
            'card_type' => 'credit',
            'brand' => 'visa',
            'last_4_digits' => '4242',
        ])
            ->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'type',
                    'name',
                    'display_name',
                    'masked_details',
                    'is_linked',
                    'is_valid_for_transactions',
                    'state',
                ],
            ]);

        $methodId = $response->json('data.id');

        $this->assertDatabaseHas('cards', [
            'id' => $methodId,
            'user_id' => $owner->id,
            'name' => 'Visa Compartida',
        ]);

        $this->assertDatabaseHas('card_workspace', [
            'workspace_id' => $workspace->id,
            'card_id' => $methodId,
            'is_shared' => true,
            'is_active' => true,
        ]);
    }

    public function test_unlink_without_usage_deletes_pivot(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $method = OtherPaymentMethod::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
        ]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        $this->actingAsUser($owner);

        $this->patchJson("/api/v1/workspaces/{$workspace->id}/payment-methods/{$method->id}/link", [
            'is_linked' => false,
        ])->assertNoContent();

        $this->assertDatabaseMissing('other_payment_method_workspace', [
            'workspace_id' => $workspace->id,
            'other_payment_method_id' => $method->id,
        ]);
    }

    public function test_unlink_with_usage_keeps_read_only_linked_state(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $method = OtherPaymentMethod::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
        ]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
            'category_id' => $workspace->enabledCategories()->first()->id,
            'payment_type' => 'other',
            'payment_instrument_id' => $method->id,
        ]);

        $this->actingAsUser($owner);

        $this->patchJson("/api/v1/workspaces/{$workspace->id}/payment-methods/{$method->id}/link", [
            'is_linked' => false,
        ])->assertNoContent();

        $this->assertDatabaseHas('other_payment_method_workspace', [
            'workspace_id' => $workspace->id,
            'other_payment_method_id' => $method->id,
            'is_shared' => false,
            'is_active' => false,
        ]);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/payment-methods")
            ->assertOk();

        $item = collect($response->json('data'))->firstWhere('id', $method->id);

        $this->assertSame('read_only_linked', $item['state']);
        $this->assertFalse($item['is_valid_for_transactions']);
    }

    public function test_cash_is_not_exposed_in_workspace_payment_methods(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $this->actingAsUser($owner);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/payment-methods")
            ->assertOk();

        $types = array_column($response->json('data'), 'type');

        $this->assertNotContains('cash', $types);
    }

    public function test_valid_endpoint_returns_only_linked_and_active_methods(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'card' => $linkedCard] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $inactiveMethod = OtherPaymentMethod::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
        ]);
        $inactiveMethod->workspaces()->attach($workspace->id, ['is_shared' => false, 'is_active' => false]);

        $unlinkedMethod = OtherPaymentMethod::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
        ]);

        $this->actingAsUser($owner);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/payment-methods/valid")
            ->assertOk();

        $types = array_column($response->json('data'), 'type');
        $ids = array_column($response->json('data'), 'id');
        $cashItem = collect($response->json('data'))->firstWhere('type', 'cash');

        $this->assertContains('cash', $types);
        $this->assertContains($linkedCard->id, $ids);
        $this->assertNotContains($inactiveMethod->id, $ids);
        $this->assertNotContains($unlinkedMethod->id, $ids);
        $this->assertNull($cashItem['id']);
        $this->assertTrue($cashItem['is_valid_for_transactions']);
    }

    public function test_cards_mask_last_4_digits_in_workspace_responses(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $card = $owner->cards()->create([
            'workspace_id' => $workspace->id,
            'name' => 'Mastercard',
            'card_type' => 'credit',
            'brand' => 'mastercard',
            'last_4_digits' => '9999',
        ]);
        $card->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        $this->actingAsUser($owner);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/payment-methods")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'name',
                        'display_name',
                        'masked_details',
                        'is_linked',
                        'is_valid_for_transactions',
                        'state',
                        'is_in_use_in_workspace',
                    ],
                ],
            ]);

        $item = collect($response->json('data'))->firstWhere('id', $card->id);

        $this->assertSame('****9999', $item['masked_details']);
    }

    public function test_bulk_unlink_all_returns_operation_result_structure(): void
    {
        ['user' => $owner, 'workspace' => $workspace, 'card' => $linkedCard] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $method = OtherPaymentMethod::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $owner->id,
        ]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        $this->actingAsUser($owner);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/payment-methods/link-bulk", [
            'operation' => 'unlink_all',
        ])->assertOk()
            ->assertJsonStructure([
                'operation',
                'total',
                'processed',
                'blocked',
                'processed_method_ids',
                'blocked_method_ids',
            ]);

        $response
            ->assertJsonPath('operation', 'unlink_all')
            ->assertJsonPath('blocked', 0);

        $processedIds = $response->json('processed_method_ids');

        $this->assertContains($linkedCard->id, $processedIds);
        $this->assertContains($method->id, $processedIds);
        $this->assertSame([], $response->json('blocked_method_ids'));
    }

    public function test_updating_link_with_unknown_workspace_payment_method_returns_domain_404(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace(['type' => 'familiar']);
        $this->actingAsUser($owner);

        $missingMethodId = (string) Str::uuid();

        $this->patchJson("/api/v1/workspaces/{$workspace->id}/payment-methods/{$missingMethodId}/link", [
            'is_linked' => false,
        ])
            ->assertNotFound()
            ->assertJsonPath('status', 404)
            ->assertJsonPath('code', 'workspace_payment_method_not_found')
            ->assertJsonPath('message', 'Workspace payment method not found.');
    }
}
