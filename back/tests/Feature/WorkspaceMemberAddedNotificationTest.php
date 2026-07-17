<?php

namespace Tests\Feature;

use App\Models\Workspace;
use App\Notifications\WorkspaceMemberAddedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class WorkspaceMemberAddedNotificationTest extends TestCase
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

    public function test_adding_a_member_to_a_workspace_sends_added_notification_to_the_new_member(): void
    {
        Notification::fake();

        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');

        $newMember = \App\Models\User::factory()->create();
        $newMember->assignRole('user');

        $this->actingAsUser($owner);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/members", [
            'email' => $newMember->email,
            'role' => 'guest',
        ]);

        $response->assertCreated();

        // The new member must receive the notification; the inviter (owner) must not.
        Notification::assertSentTo($newMember, WorkspaceMemberAddedNotification::class);
        Notification::assertNotSentTo($owner, WorkspaceMemberAddedNotification::class);
    }

    public function test_added_notification_uses_mail_channel_only(): void
    {
        Notification::fake();

        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');

        $newMember = \App\Models\User::factory()->create();
        $newMember->assignRole('user');

        $this->actingAsUser($owner);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/members", [
            'email' => $newMember->email,
            'role' => 'guest',
        ])->assertCreated();

        // The notification only defines the mail channel — even with a FCM device
        // attached, the FCM channel is NOT registered on `via()`.
        $device = \App\Models\PushDevice::factory()->create([
            'user_id' => $newMember->id,
            'notification_permission' => 'granted',
            'revoked_at' => null,
        ]);
        $this->assertNotNull($device);

        Notification::assertSentTo(
            $newMember,
            WorkspaceMemberAddedNotification::class,
            function (WorkspaceMemberAddedNotification $notification) use ($newMember): bool {
                $channels = $notification->via($newMember);

                return $channels === ['mail'];
            }
        );
    }

    public function test_added_notification_mail_payload_contains_workspace_and_inviter_info(): void
    {
        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace(['name' => 'Equipo Dev']);
        $owner->assignRole('premium');

        $newMember = \App\Models\User::factory()->create();
        $newMember->assignRole('user');

        $notification = new WorkspaceMemberAddedNotification($workspace, $owner);

        $mail = $notification->toMail($newMember);

        $this->assertInstanceOf(MailMessage::class, $mail);

        // The subject embeds the workspace name.
        $this->assertStringContainsString('Equipo Dev', (string) $mail->subject);

        // The intro line embeds the inviter's name.
        $this->assertStringContainsString($owner->name, $mail->introLines[0] ?? '');

        // The mail body contains the workspace name.
        $bodyLines = array_map('strval', $mail->introLines);
        $this->assertTrue(
            (bool) array_filter($bodyLines, fn (string $line): bool => str_contains($line, 'Equipo Dev')),
            'Mail body should mention the workspace name.',
        );

        // The action button is the configured frontend URL.
        $this->assertSame(
            rtrim((string) config('app.frontend_url'), '/'),
            (string) $mail->actionUrl,
        );
    }

    public function test_added_notification_is_not_sent_when_adding_an_existing_member_fails(): void
    {
        Notification::fake();

        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');

        // The owner is already a member of their own workspace; trying to re-add
        // them must trigger 422 and NOT send any notification.
        $this->actingAsUser($owner);

        $this->postJson("/api/v1/workspaces/{$workspace->id}/members", [
            'email' => $owner->email,
        ])->assertUnprocessable();

        Notification::assertNothingSent();
    }
}
