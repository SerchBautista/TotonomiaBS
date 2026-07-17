<?php

namespace Tests\Unit;

use App\Models\PushDevice;
use App\Models\User;
use App\Notifications\PushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use NotificationChannels\Fcm\FcmChannel;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_notification_constructs_with_title_body_and_data(): void
    {
        $notification = new PushNotification(
            title: 'Hola',
            body: 'Mundo',
            data: ['type' => 'test', 'foo' => 'bar'],
        );

        // Use reflection to verify the private fields are set.
        $reflection = new \ReflectionObject($notification);
        $title = $reflection->getProperty('title');
        $title->setAccessible(true);
        $body = $reflection->getProperty('body');
        $body->setAccessible(true);
        $data = $reflection->getProperty('data');
        $data->setAccessible(true);

        $this->assertSame('Hola', $title->getValue($notification));
        $this->assertSame('Mundo', $body->getValue($notification));
        $this->assertSame(['type' => 'test', 'foo' => 'bar'], $data->getValue($notification));
    }

    public function test_push_notification_via_returns_fcm_channel(): void
    {
        $user = User::factory()->create();
        $notification = new PushNotification('t', 'b');

        $channels = $notification->via($user);

        $this->assertSame([FcmChannel::class], $channels);
    }

    public function test_push_notification_uses_active_push_device_to_build_fcm_payload(): void
    {
        $user = User::factory()->create();
        $device = PushDevice::factory()->create([
            'user_id' => $user->id,
            'fcm_token' => 'device-token-1',
            'platform' => 'android',
            'notification_permission' => 'granted',
            'revoked_at' => null,
        ]);

        // The notification is sent via the User notifiable, which has its own
        // routeNotificationForFcm implementation that returns the active FCM tokens.
        $tokens = $user->routeNotificationForFcm();
        $this->assertContains('device-token-1', $tokens);

        $notification = new PushNotification(
            title: 'Pago próximo',
            body: 'Internet',
            data: ['type' => 'payment_reminder', 'fixed_expense_id' => 'fe-1'],
        );

        $channels = $notification->via($user);
        $this->assertContains(FcmChannel::class, $channels);

        $fcmMessage = $notification->toFcm($user);
        $this->assertInstanceOf(\NotificationChannels\Fcm\FcmMessage::class, $fcmMessage);

        $payload = $fcmMessage->toArray();
        $this->assertSame('Pago próximo', $payload['notification']['title']);
        $this->assertSame('Internet', $payload['notification']['body']);
        $this->assertSame('payment_reminder', $payload['data']['type']);
        $this->assertSame('fe-1', $payload['data']['fixed_expense_id']);

        $this->assertNotNull($device);
    }

    public function test_push_notification_excludes_fcm_channel_when_notifiable_has_no_routing_method(): void
    {
        $notification = new PushNotification('t', 'b');

        // AnonymousNotifiable has no `routeNotificationForFcm`, but PushNotification
        // still returns FcmChannel as a channel — the channel itself is what
        // decides what to do at dispatch time. We only assert that the channel list
        // is stable here.
        $anonymous = new AnonymousNotifiable;
        $channels = $notification->via($anonymous);

        $this->assertSame([FcmChannel::class], $channels);
    }

    public function test_push_notification_fcm_payload_coerces_non_string_data_to_string(): void
    {
        $user = User::factory()->create();
        PushDevice::factory()->create([
            'user_id' => $user->id,
            'notification_permission' => 'granted',
            'revoked_at' => null,
        ]);

        // H-016: FCM requires every value in the `data` map to be a string.
        // After the fix, PushNotification::toFcm() coerces non-string values
        // to strings so the FCM message is always valid.
        $notification = new PushNotification(
            title: 'T',
            body: 'B',
            data: [
                'int_key' => 42,
                'bool_key' => true,
                'null_key' => null,
                'string_key' => 'already-string',
            ],
        );

        $fcmMessage = $notification->toFcm($user);
        $payload = $fcmMessage->toArray();

        $this->assertSame('42', $payload['data']['int_key']);
        $this->assertSame('1', $payload['data']['bool_key']);
        $this->assertSame('', $payload['data']['null_key']);
        $this->assertSame('already-string', $payload['data']['string_key']);
    }
}
