<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Category;
use App\Models\Expense;
use App\Models\FixedExpense;
use App\Models\OtherPaymentMethod;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class MyPaymentMethodsCrudTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_user_payment_methods_index_lists_only_current_shared_workspaces_in_summary(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $method = OtherPaymentMethod::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'name' => 'Transferencia',
        ]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        $sharedWorkspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $sharedWorkspace->members()->attach($user->id, ['role' => 'admin']);
        $method->workspaces()->attach($sharedWorkspace->id, ['is_shared' => true, 'is_active' => true]);

        $historicalWorkspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $historicalWorkspace->members()->attach($user->id, ['role' => 'admin']);
        $method->workspaces()->attach($historicalWorkspace->id, ['is_shared' => false, 'is_active' => false]);

        $this->actingAsUser($user);

        $response = $this->getJson('/api/v1/user/payment-methods')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'name',
                        'display_name',
                        'masked_details',
                        'linked_workspaces_count',
                        'linked_workspaces' => [
                            '*' => ['id', 'name'],
                        ],
                    ],
                ],
            ]);

        $item = collect($response->json('data'))->firstWhere('id', $method->id);
        $linkedWorkspaceIds = collect($item['linked_workspaces'])->pluck('id')->all();

        $this->assertSame(2, $item['linked_workspaces_count']);
        $this->assertCount(2, $item['linked_workspaces']);
        $this->assertContains($workspace->id, $linkedWorkspaceIds);
        $this->assertContains($sharedWorkspace->id, $linkedWorkspaceIds);
        $this->assertNotContains($historicalWorkspace->id, $linkedWorkspaceIds);
    }

    public function test_user_can_create_personal_card_in_default_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        // createUserWithWorkspace returns the FIRST created workspace which IS the default for the user.
        $this->actingAsUser($user);

        $response = $this->postJson('/api/v1/user/payment-methods', [
            'type' => 'card',
            'name' => 'Visa Personal',
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
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'name' => 'Visa Personal',
        ]);

        $this->assertDatabaseHas('card_workspace', [
            'workspace_id' => $workspace->id,
            'card_id' => $methodId,
            'is_shared' => true,
            'is_active' => true,
        ]);

        $this->assertSame('card', $response->json('data.type'));
        $this->assertSame('****4242', $response->json('data.masked_details'));
    }

    public function test_user_can_create_personal_other_payment_method_in_default_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $response = $this->postJson('/api/v1/user/payment-methods', [
            'type' => 'other',
            'name' => 'Transferencia',
            'description' => 'Cuenta de ahorro personal',
        ])->assertCreated();

        $methodId = $response->json('data.id');

        $this->assertDatabaseHas('other_payment_methods', [
            'id' => $methodId,
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
            'name' => 'Transferencia',
        ]);

        $this->assertDatabaseHas('other_payment_method_workspace', [
            'workspace_id' => $workspace->id,
            'other_payment_method_id' => $methodId,
            'is_shared' => true,
            'is_active' => true,
        ]);
    }

    public function test_create_personal_card_requires_last_4_digits(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->postJson('/api/v1/user/payment-methods', [
            'type' => 'card',
            'name' => 'Incompleta',
            'card_type' => 'debit',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonValidationErrors(['last_4_digits'], 'fieldErrors');
    }

    public function test_create_personal_card_requires_card_type(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->postJson('/api/v1/user/payment-methods', [
            'type' => 'card',
            'name' => 'Sin tipo',
            'last_4_digits' => '1234',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonValidationErrors(['card_type'], 'fieldErrors');
    }

    public function test_create_personal_payment_method_rejects_invalid_type(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->postJson('/api/v1/user/payment-methods', [
            'type' => 'cash',
            'name' => 'Invalid',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonValidationErrors(['type'], 'fieldErrors');
    }

    public function test_unauthenticated_user_cannot_create_personal_payment_method(): void
    {
        $this->postJson('/api/v1/user/payment-methods', [
            'type' => 'other',
            'name' => 'Anon',
        ])->assertStatus(401);
    }

    public function test_user_can_delete_own_personal_card(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $card = Card::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
        $card->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);
        $this->actingAsUser($user);

        $this->deleteJson("/api/v1/user/payment-methods/{$card->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('cards', ['id' => $card->id]);
        $this->assertDatabaseMissing('card_workspace', ['card_id' => $card->id]);
    }

    public function test_user_can_delete_own_personal_other_payment_method(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $method = OtherPaymentMethod::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);
        $this->actingAsUser($user);

        $this->deleteJson("/api/v1/user/payment-methods/{$method->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('other_payment_methods', ['id' => $method->id]);
        $this->assertDatabaseMissing('other_payment_method_workspace', ['other_payment_method_id' => $method->id]);
    }

    public function test_user_cannot_delete_another_users_payment_method(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $stranger = User::factory()->create();
        $stranger->assignRole('user');
        $card = Card::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $stranger->id]);

        $this->actingAsUser($owner);

        $this->deleteJson("/api/v1/user/payment-methods/{$card->id}")
            ->assertNotFound()
            ->assertJsonPath('status', 404)
            ->assertJsonPath('code', 'user_payment_method_not_found');
    }

    public function test_delete_unknown_payment_method_returns_404(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $missingId = (string) Str::uuid();

        $this->deleteJson("/api/v1/user/payment-methods/{$missingId}")
            ->assertNotFound()
            ->assertJsonPath('status', 404)
            ->assertJsonPath('code', 'user_payment_method_not_found');
    }

    public function test_unauthenticated_user_cannot_delete_personal_payment_method(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $card = Card::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

        $this->deleteJson("/api/v1/user/payment-methods/{$card->id}")
            ->assertStatus(401);
    }

    /**
     * M-2: A user with no default workspace and no active membership must
     * receive a 409 conflict with code `user_has_no_default_workspace` when
     * trying to create a personal payment method.
     */
    public function test_user_with_no_default_workspace_cannot_create_personal_payment_method(): void
    {
        $user = User::factory()->create();
        $user->assignRole('user');
        $this->actingAsUser($user);

        $this->withHeader('X-Request-Id', 'req-user-payment-method-no-workspace-409')
            ->postJson('/api/v1/user/payment-methods', [
                'type' => 'other',
                'name' => 'No workspace',
            ])
            ->assertStatus(409)
            ->assertJsonPath('status', 409)
            ->assertJsonPath('code', 'user_has_no_default_workspace')
            ->assertJsonPath('message', __('api.errors.user_has_no_default_workspace'))
            ->assertJsonPath('request_id', 'req-user-payment-method-no-workspace-409');
    }

    /**
     * M-3: Deleting a personal card that is referenced by an expense must
     * fail with 409 `payment_method_in_use`.
     */
    public function test_delete_personal_card_in_use_returns_409(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'card' => $card] = $this->createUserWithWorkspace();

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => Category::factory()->forUser($user)->create()->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
        ]);

        $this->actingAsUser($user);

        $this->withHeader('X-Request-Id', 'req-user-payment-method-in-use-409')
            ->deleteJson("/api/v1/user/payment-methods/{$card->id}")
            ->assertStatus(409)
            ->assertJsonPath('status', 409)
            ->assertJsonPath('code', 'payment_method_in_use')
            ->assertJsonPath('message', __('api.errors.payment_method_in_use'))
            ->assertJsonPath('request_id', 'req-user-payment-method-in-use-409');

        $this->assertDatabaseHas('cards', ['id' => $card->id, 'deleted_at' => null]);
    }

    /**
     * M-3: Deleting a personal OtherPaymentMethod that is referenced by a
     * fixed expense must fail with 409 `payment_method_in_use`.
     */
    public function test_delete_personal_other_payment_method_in_use_returns_409(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $method = OtherPaymentMethod::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        FixedExpense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => Category::factory()->forUser($user)->create()->id,
            'payment_type' => 'other',
            'payment_instrument_id' => $method->id,
        ]);

        $this->actingAsUser($user);

        $this->deleteJson("/api/v1/user/payment-methods/{$method->id}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'payment_method_in_use');

        $this->assertDatabaseHas('other_payment_methods', ['id' => $method->id, 'deleted_at' => null]);
    }

    public function test_user_can_update_own_personal_card(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $card = Card::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'name' => 'Old Name',
            'card_type' => 'credit',
            'brand' => 'visa',
            'last_4_digits' => '1111',
        ]);
        $card->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);
        $this->actingAsUser($user);

        $response = $this->putJson("/api/v1/user/payment-methods/{$card->id}", [
            'type' => 'card',
            'name' => 'New Card Name',
            'card_type' => 'debit',
            'brand' => 'mastercard',
            'last_4_digits' => '4242',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $card->id)
            ->assertJsonPath('data.type', 'card')
            ->assertJsonPath('data.name', 'New Card Name')
            ->assertJsonPath('data.display_name', 'New Card Name')
            ->assertJsonPath('data.masked_details', '****4242');

        $this->assertDatabaseHas('cards', [
            'id' => $card->id,
            'user_id' => $user->id,
            'name' => 'New Card Name',
            'card_type' => 'debit',
            'brand' => 'mastercard',
        ]);

        // Pivot must remain unchanged.
        $this->assertDatabaseHas('card_workspace', [
            'card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'is_shared' => true,
            'is_active' => true,
        ]);
    }

    public function test_user_can_update_own_personal_other_payment_method(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $method = OtherPaymentMethod::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'name' => 'PayPal',
            'description' => 'Old description',
        ]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);
        $this->actingAsUser($user);

        $response = $this->putJson("/api/v1/user/payment-methods/{$method->id}", [
            'type' => 'other',
            'name' => 'Bank Transfer',
            'description' => 'Updated description',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $method->id)
            ->assertJsonPath('data.type', 'other')
            ->assertJsonPath('data.name', 'Bank Transfer')
            ->assertJsonPath('data.display_name', 'Bank Transfer');

        $this->assertDatabaseHas('other_payment_methods', [
            'id' => $method->id,
            'user_id' => $user->id,
            'name' => 'Bank Transfer',
            'description' => 'Updated description',
        ]);

        $this->assertDatabaseHas('other_payment_method_workspace', [
            'other_payment_method_id' => $method->id,
            'workspace_id' => $workspace->id,
            'is_shared' => true,
            'is_active' => true,
        ]);
    }

    public function test_update_personal_payment_method_does_not_touch_pivot_flags(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $card = Card::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
        // Attach with custom pivot values: is_shared=false, is_active=false.
        $card->workspaces()->attach($workspace->id, ['is_shared' => false, 'is_active' => false]);
        $this->actingAsUser($user);

        $this->putJson("/api/v1/user/payment-methods/{$card->id}", [
            'type' => 'card',
            'name' => 'Updated',
            'card_type' => 'credit',
            'last_4_digits' => '9999',
        ])->assertOk();

        // Pivot must NOT be flipped by the update endpoint.
        $this->assertDatabaseHas('card_workspace', [
            'card_id' => $card->id,
            'workspace_id' => $workspace->id,
            'is_shared' => false,
            'is_active' => false,
        ]);
    }

    public function test_update_personal_payment_method_rejects_missing_name(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $card = Card::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
        $this->actingAsUser($user);

        $this->putJson("/api/v1/user/payment-methods/{$card->id}", [
            'type' => 'card',
            'card_type' => 'credit',
            'last_4_digits' => '1234',
        ])
            ->assertStatus(422)
            ->assertJsonPath('status', 422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonValidationErrors(['name'], 'fieldErrors');
    }

    public function test_update_personal_card_rejects_missing_last_4_digits(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $card = Card::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
        $this->actingAsUser($user);

        $this->putJson("/api/v1/user/payment-methods/{$card->id}", [
            'type' => 'card',
            'name' => 'No digits',
            'card_type' => 'debit',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonValidationErrors(['last_4_digits'], 'fieldErrors');
    }

    public function test_update_personal_card_rejects_missing_card_type(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $card = Card::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
        $this->actingAsUser($user);

        $this->putJson("/api/v1/user/payment-methods/{$card->id}", [
            'type' => 'card',
            'name' => 'No card type',
            'last_4_digits' => '0000',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonValidationErrors(['card_type'], 'fieldErrors');
    }

    public function test_update_personal_payment_method_rejects_invalid_type(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $card = Card::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);
        $this->actingAsUser($user);

        $this->putJson("/api/v1/user/payment-methods/{$card->id}", [
            'type' => 'crypto',
            'name' => 'Bad type',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonValidationErrors(['type'], 'fieldErrors');
    }

    public function test_user_cannot_update_another_users_personal_payment_method(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $stranger = User::factory()->create();
        $stranger->assignRole('user');
        $card = Card::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $stranger->id,
        ]);

        $this->actingAsUser($owner);

        $this->putJson("/api/v1/user/payment-methods/{$card->id}", [
            'type' => 'card',
            'name' => 'Hijacked',
            'card_type' => 'credit',
            'last_4_digits' => '1111',
        ])
            ->assertNotFound()
            ->assertJsonPath('status', 404)
            ->assertJsonPath('code', 'user_payment_method_not_found');
    }

    public function test_update_unknown_personal_payment_method_returns_404(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $missingId = (string) Str::uuid();

        $this->putJson("/api/v1/user/payment-methods/{$missingId}", [
            'type' => 'other',
            'name' => 'Missing',
        ])
            ->assertNotFound()
            ->assertJsonPath('status', 404)
            ->assertJsonPath('code', 'user_payment_method_not_found');
    }

    public function test_unauthenticated_user_cannot_update_personal_payment_method(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $card = Card::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

        $this->putJson("/api/v1/user/payment-methods/{$card->id}", [
            'type' => 'card',
            'name' => 'Anon',
            'card_type' => 'credit',
            'last_4_digits' => '1234',
        ])->assertStatus(401);
    }

    public function test_unverified_user_cannot_update_personal_payment_method(): void
    {
        $this->seed();

        $user = User::factory()->unverified()->create();
        $user->assignRole('user');
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => 'owner']);
        $user->forceFill(['default_workspace_id' => $workspace->id])->save();
        $card = Card::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);

        \Laravel\Passport\Passport::actingAs($user, ['*'], 'api');

        $this->putJson("/api/v1/user/payment-methods/{$card->id}", [
            'type' => 'card',
            'name' => 'Should be blocked',
            'card_type' => 'credit',
            'last_4_digits' => '1234',
        ])
            ->assertStatus(403)
            ->assertJsonPath('status', 403)
            ->assertJsonPath('code', 'email_not_verified');
    }

    public function test_create_personal_card_with_workspace_ids_links_to_workspaces(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $secondWorkspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $secondWorkspace->members()->attach($user->id, ['role' => 'owner']);
        $this->actingAsUser($user);

        $response = $this->postJson('/api/v1/user/payment-methods', [
            'type' => 'card',
            'name' => 'Shared Card',
            'card_type' => 'credit',
            'last_4_digits' => '4242',
            'workspace_ids' => [$workspace->id, $secondWorkspace->id],
        ])->assertCreated();

        $methodId = $response->json('data.id');

        $this->assertDatabaseHas('card_workspace', [
            'card_id' => $methodId,
            'workspace_id' => $workspace->id,
            'is_shared' => true,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('card_workspace', [
            'card_id' => $methodId,
            'workspace_id' => $secondWorkspace->id,
            'is_shared' => true,
            'is_active' => true,
        ]);
    }

    public function test_create_personal_payment_method_auto_links_when_user_owns_single_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $response = $this->postJson('/api/v1/user/payment-methods', [
            'type' => 'other',
            'name' => 'Auto Linked',
        ])->assertCreated();

        $methodId = $response->json('data.id');

        $this->assertDatabaseHas('other_payment_method_workspace', [
            'other_payment_method_id' => $methodId,
            'workspace_id' => $workspace->id,
            'is_shared' => true,
            'is_active' => true,
        ]);
    }

    public function test_update_personal_payment_method_syncs_workspaces(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $secondWorkspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $secondWorkspace->members()->attach($user->id, ['role' => 'owner']);
        $method = OtherPaymentMethod::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);
        $this->actingAsUser($user);

        $this->putJson("/api/v1/user/payment-methods/{$method->id}", [
            'type' => 'other',
            'name' => 'Updated',
            'workspace_ids' => [$secondWorkspace->id],
        ])->assertOk();

        $this->assertDatabaseMissing('other_payment_method_workspace', [
            'other_payment_method_id' => $method->id,
            'workspace_id' => $workspace->id,
        ]);

        $this->assertDatabaseHas('other_payment_method_workspace', [
            'other_payment_method_id' => $method->id,
            'workspace_id' => $secondWorkspace->id,
            'is_shared' => true,
            'is_active' => true,
        ]);
    }

    public function test_update_personal_payment_method_rejects_foreign_workspace(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $stranger = User::factory()->create();
        $stranger->assignRole('user');
        $foreignWorkspace = Workspace::factory()->create(['owner_id' => $stranger->id]);
        $method = OtherPaymentMethod::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);
        $this->actingAsUser($user);

        $this->putJson("/api/v1/user/payment-methods/{$method->id}", [
            'type' => 'other',
            'name' => 'Updated',
            'workspace_ids' => [$foreignWorkspace->id],
        ])
            ->assertForbidden()
            ->assertJsonPath('status', 403)
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_sync_personal_payment_method_workspaces_keeps_read_only_when_in_use(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $method = OtherPaymentMethod::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        Expense::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => Category::factory()->forUser($user)->create()->id,
            'payment_type' => 'other',
            'payment_instrument_id' => $method->id,
        ]);

        $this->actingAsUser($user);

        $this->patchJson("/api/v1/user/payment-methods/{$method->id}/workspaces", [
            'workspace_ids' => [],
        ])->assertOk();

        $this->assertDatabaseHas('other_payment_method_workspace', [
            'other_payment_method_id' => $method->id,
            'workspace_id' => $workspace->id,
            'is_shared' => false,
            'is_active' => false,
        ]);
    }

    public function test_sync_personal_payment_method_workspaces_rejects_foreign_workspace(): void
    {
        ['user' => $user] = $this->createUserWithWorkspace();
        $stranger = User::factory()->create();
        $stranger->assignRole('user');
        $foreignWorkspace = Workspace::factory()->create(['owner_id' => $stranger->id]);
        $method = OtherPaymentMethod::factory()->create([
            'workspace_id' => Workspace::factory()->create(['owner_id' => $user->id])->id,
            'user_id' => $user->id,
        ]);
        $this->actingAsUser($user);

        $this->patchJson("/api/v1/user/payment-methods/{$method->id}/workspaces", [
            'workspace_ids' => [$foreignWorkspace->id],
        ])
            ->assertForbidden()
            ->assertJsonPath('status', 403)
            ->assertJsonPath('code', 'forbidden');
    }

    public function test_sync_personal_payment_method_workspaces_rejects_legacy_foreign_pivot_without_mutating_it(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $stranger = User::factory()->create();
        $stranger->assignRole('user');
        $foreignWorkspace = Workspace::factory()->create(['owner_id' => $stranger->id]);
        $method = OtherPaymentMethod::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
        ]);
        $method->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);
        $method->workspaces()->attach($foreignWorkspace->id, ['is_shared' => false, 'is_active' => false]);

        $this->actingAsUser($user);

        $this->patchJson("/api/v1/user/payment-methods/{$method->id}/workspaces", [
            'workspace_ids' => [$workspace->id],
        ])
            ->assertForbidden()
            ->assertJsonPath('status', 403)
            ->assertJsonPath('code', 'forbidden');

        $this->assertDatabaseHas('other_payment_method_workspace', [
            'other_payment_method_id' => $method->id,
            'workspace_id' => $foreignWorkspace->id,
            'is_shared' => false,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('other_payment_method_workspace', [
            'other_payment_method_id' => $method->id,
            'workspace_id' => $workspace->id,
            'is_shared' => true,
            'is_active' => true,
        ]);
    }
}
