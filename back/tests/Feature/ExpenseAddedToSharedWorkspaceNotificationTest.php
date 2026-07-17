<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\PushDevice;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\ExpenseAddedToSharedWorkspaceNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class ExpenseAddedToSharedWorkspaceNotificationTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    /**
     * Build a 3-member shared workspace on top of the default createUserWithWorkspace,
     * returning the owner, the workspace, the category, the card, and two extra members.
     *
     * @return array{user: User, workspace: Workspace, category: Category, card: \App\Models\Card, memberB: User, memberC: User}
     */
    private function createSharedWorkspaceWithMembers(): array
    {
        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace(['type' => 'familiar']);

        $memberB = User::factory()->create(['name' => 'Bob']);
        $memberB->assignRole('user');
        $workspace->members()->attach($memberB->id, ['role' => 'admin']);

        $memberC = User::factory()->create(['name' => 'Carol']);
        $memberC->assignRole('user');
        $workspace->members()->attach($memberC->id, ['role' => 'member']);

        return [
            'user' => $user,
            'workspace' => $workspace,
            'category' => $cat,
            'card' => $card,
            'memberB' => $memberB,
            'memberC' => $memberC,
        ];
    }

    public function test_posting_expense_in_shared_workspace_notifies_other_members_via_http(): void
    {
        Notification::fake();

        ['user' => $author, 'workspace' => $workspace, 'category' => $cat, 'card' => $card, 'memberB' => $b, 'memberC' => $c] = $this->createSharedWorkspaceWithMembers();
        $this->actingAsUser($author);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '99.50',
            'date' => now()->toDateString(),
            'description' => 'Cena con el equipo',
        ]);

        $response->assertCreated();

        // Author must not be notified.
        Notification::assertNotSentTo($author, ExpenseAddedToSharedWorkspaceNotification::class);

        // Other members do.
        Notification::assertSentTo($b, ExpenseAddedToSharedWorkspaceNotification::class);
        Notification::assertSentTo($c, ExpenseAddedToSharedWorkspaceNotification::class);

        // FCM channel is selected only when the notifiable has active FCM tokens.
        // (B and C have no devices here, so the push channel should be excluded.)
        Notification::assertSentTo(
            $b,
            ExpenseAddedToSharedWorkspaceNotification::class,
            function (ExpenseAddedToSharedWorkspaceNotification $notification) use ($b): bool {
                $channels = $notification->via($b);

                return in_array('database', $channels, true)
                    && ! in_array(\NotificationChannels\Fcm\FcmChannel::class, $channels, true);
            }
        );
    }

    public function test_posting_expense_in_shared_workspace_filters_fcm_by_active_granted_tokens(): void
    {
        Notification::fake();

        ['user' => $author, 'workspace' => $workspace, 'category' => $cat, 'card' => $card, 'memberB' => $b, 'memberC' => $c] = $this->createSharedWorkspaceWithMembers();

        // Member B has a granted/active device — push channel must be enabled.
        PushDevice::factory()->create([
            'user_id' => $b->id,
            'fcm_token' => 'b-granted-active',
            'notification_permission' => 'granted',
            'revoked_at' => null,
        ]);

        // Member C has a denied device — push channel must be excluded,
        // in-app (database) channel must still fire.
        PushDevice::factory()->create([
            'user_id' => $c->id,
            'fcm_token' => 'c-denied',
            'notification_permission' => 'denied',
            'revoked_at' => null,
        ]);

        $this->actingAsUser($author);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '15.00',
            'date' => now()->toDateString(),
            'description' => 'Café rápido',
        ])->assertCreated();

        // B receives both in-app and push.
        Notification::assertSentTo(
            $b,
            ExpenseAddedToSharedWorkspaceNotification::class,
            function (ExpenseAddedToSharedWorkspaceNotification $notification) use ($b): bool {
                $channels = $notification->via($b);

                return in_array('database', $channels, true)
                    && in_array(\NotificationChannels\Fcm\FcmChannel::class, $channels, true);
            }
        );

        // C receives only in-app.
        Notification::assertSentTo(
            $c,
            ExpenseAddedToSharedWorkspaceNotification::class,
            function (ExpenseAddedToSharedWorkspaceNotification $notification) use ($c): bool {
                $channels = $notification->via($c);

                return in_array('database', $channels, true)
                    && ! in_array(\NotificationChannels\Fcm\FcmChannel::class, $channels, true);
            }
        );
    }

    public function test_posting_expense_in_personal_workspace_does_not_notify_anyone_via_http(): void
    {
        Notification::fake();

        ['user' => $user, 'workspace' => $workspace, 'category' => $cat, 'card' => $card] = $this->createUserWithWorkspace(['type' => 'personal']);
        $this->actingAsUser($user);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '10.00',
            'date' => now()->toDateString(),
            'description' => 'Gasto personal',
        ])->assertCreated();

        Notification::assertNothingSent();
    }

    public function test_non_member_cannot_post_expense_and_does_not_trigger_notification(): void
    {
        Notification::fake();

        // Owner A has the shared workspace with B and C.
        ['user' => $owner, 'workspace' => $workspace, 'category' => $cat, 'card' => $card, 'memberB' => $b, 'memberC' => $c] = $this->createSharedWorkspaceWithMembers();

        // User D is a complete outsider (not a member of the workspace).
        $outsider = User::factory()->create();
        $outsider->assignRole('user');
        $this->actingAsUser($outsider);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/expenses", [
            'category_id' => $cat->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '10.00',
            'date' => now()->toDateString(),
            'description' => 'Intento de gasto no autorizado',
        ]);

        // Authorization gate must block the request.
        $response->assertForbidden();

        // No notification must be fired on a forbidden request.
        Notification::assertNothingSent();
    }

    public function test_posting_expense_in_workspace_does_not_notify_members_of_a_different_workspace(): void
    {
        Notification::fake();

        // Workspace A: owner + B + C (the expense will land here).
        ['user' => $owner, 'workspace' => $workspaceA, 'category' => $catA, 'card' => $cardA, 'memberB' => $b, 'memberC' => $c] = $this->createSharedWorkspaceWithMembers();

        // Workspace B: another owner, but B (and the author) is also a member of B.
        // Authoring an expense in A must NOT notify the members of B.
        $ownerB = User::factory()->create();
        $ownerB->assignRole('user');
        $workspaceB = Workspace::factory()->create([
            'owner_id' => $ownerB->id,
            'type' => 'familiar',
            'name' => 'Otro espacio',
        ]);
        $workspaceB->members()->attach($ownerB->id, ['role' => 'owner']);
        $workspaceB->members()->attach($b->id, ['role' => 'admin']);
        $workspaceB->members()->attach($owner->id, ['role' => 'member']);

        // Member of B that is NOT a member of A — they must not be notified.
        $memberOnlyInB = User::factory()->create();
        $memberOnlyInB->assignRole('user');
        $workspaceB->members()->attach($memberOnlyInB->id, ['role' => 'member']);

        $this->actingAsUser($owner);

        $this->postJson("/api/v1/workspaces/{$workspaceA->id}/expenses", [
            'category_id' => $catA->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $cardA->id,
            'amount' => '25.00',
            'date' => now()->toDateString(),
            'description' => 'Gasto en A',
        ])->assertCreated();

        // Members of A are notified as before.
        Notification::assertSentTo($b, ExpenseAddedToSharedWorkspaceNotification::class);
        Notification::assertSentTo($c, ExpenseAddedToSharedWorkspaceNotification::class);

        // Author must not be notified even though they are a member of B.
        Notification::assertNotSentTo($owner, ExpenseAddedToSharedWorkspaceNotification::class);

        // Members of B that are not in A must not be notified at all.
        Notification::assertNotSentTo($ownerB, ExpenseAddedToSharedWorkspaceNotification::class);
        Notification::assertNotSentTo($memberOnlyInB, ExpenseAddedToSharedWorkspaceNotification::class);

        // Total dispatch count must be exactly 2 (B and C from workspace A).
        Notification::assertSentToTimes($b, ExpenseAddedToSharedWorkspaceNotification::class, 1);
        Notification::assertSentToTimes($c, ExpenseAddedToSharedWorkspaceNotification::class, 1);
    }
}
