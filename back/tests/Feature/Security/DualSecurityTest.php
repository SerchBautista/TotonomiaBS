<?php

namespace Tests\Feature\Security;

use App\Models\Card;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class DualSecurityTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_card_last_4_digits_are_masked_in_api_response(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();

        $card = Card::factory()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'last_4_digits' => '4242',
        ]);
        $card->workspaces()->attach($workspace->id, ['is_shared' => true, 'is_active' => true]);

        $this->actingAsUser($user);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/cards");
        $response->assertOk();

        $cardData = collect($response->json('data'))->firstWhere('id', $card->id);
        $this->assertNotNull($cardData);
        // The API must mask last 4 digits, not expose them as plain text
        $this->assertEquals('****4242', $cardData['last_4_digits']);
    }

    public function test_stripe_webhook_rejects_invalid_signature(): void
    {
        $response = $this->postJson('/api/v1/webhooks/stripe', ['type' => 'checkout.session.completed'], [
            'Stripe-Signature' => 'invalid_signature',
        ]);

        // Without a valid secret configured, it returns 400
        $response->assertStatus(400);
    }

    public function test_workspace_soft_delete_preserves_audit_trail(): void
    {
        ['user' => $user, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}")->assertNoContent();

        // Soft deleted: still exists in DB
        $this->assertSoftDeleted('workspaces', ['id' => $workspace->id]);
        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);
    }

    public function test_expense_soft_delete_preserves_audit_trail(): void
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace();
        $this->actingAsUser($user);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '250.00',
            'date' => now()->toDateString(),
        ]);

        $expenseId = $response->json('data.id');

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/expenses/{$expenseId}")
            ->assertNoContent();

        $this->assertSoftDeleted('expenses', ['id' => $expenseId]);
    }
}
