<?php

namespace Tests\Feature;

use App\Notifications\WorkspaceMemberRemovedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Tests\CreatesWorkspace;
use Tests\TestCase;

class WorkspaceMemberRemovedNotificationTest extends TestCase
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

    public function test_removing_a_member_from_workspace_sends_removed_notification_to_that_member(): void
    {
        Notification::fake();

        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');

        $member = \App\Models\User::factory()->create();
        $member->assignRole('user');
        $workspace->members()->attach($member->id, ['role' => 'guest']);

        $this->actingAsUser($owner);

        $response = $this->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}");

        $response->assertNoContent();

        Notification::assertSentTo($member, WorkspaceMemberRemovedNotification::class);
        Notification::assertNotSentTo($owner, WorkspaceMemberRemovedNotification::class);
    }

    public function test_removed_notification_uses_mail_channel_only(): void
    {
        Notification::fake();

        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');

        $member = \App\Models\User::factory()->create();
        $member->assignRole('user');
        $workspace->members()->attach($member->id, ['role' => 'guest']);

        $this->actingAsUser($owner);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$member->id}")
            ->assertNoContent();

        // Even if the removed member has an active FCM device, the notification
        // does not declare the FCM channel.
        \App\Models\PushDevice::factory()->create([
            'user_id' => $member->id,
            'notification_permission' => 'granted',
            'revoked_at' => null,
        ]);

        Notification::assertSentTo(
            $member,
            WorkspaceMemberRemovedNotification::class,
            function (WorkspaceMemberRemovedNotification $notification) use ($member): bool {
                return $notification->via($member) === ['mail'];
            }
        );
    }

    public function test_removed_notification_mail_payload_contains_workspace_name(): void
    {
        ['workspace' => $workspace] = $this->createUserWithWorkspace(['name' => 'Proyecto X']);

        $member = \App\Models\User::factory()->create();
        $member->assignRole('user');

        $notification = new WorkspaceMemberRemovedNotification($workspace);
        $mail = $notification->toMail($member);

        $this->assertInstanceOf(MailMessage::class, $mail);

        // Subject is generic by design; the workspace name must be in the body.
        $allLines = array_merge(
            array_map('strval', $mail->introLines),
            array_map('strval', $mail->outroLines),
        );
        $this->assertTrue(
            (bool) array_filter($allLines, fn (string $line): bool => str_contains($line, 'Proyecto X')),
            'Mail body should mention the workspace name.',
        );
    }

    public function test_removed_notification_is_not_sent_when_removing_a_non_member(): void
    {
        Notification::fake();

        ['user' => $owner, 'workspace' => $workspace] = $this->createUserWithWorkspace();
        $owner->assignRole('premium');

        $outsider = \App\Models\User::factory()->create();
        $outsider->assignRole('user');

        $this->actingAsUser($owner);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}/members/{$outsider->id}")
            ->assertNotFound();

        Notification::assertNothingSent();
    }
}
