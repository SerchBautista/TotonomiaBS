<?php

namespace Tests\Unit;

use App\Jobs\SendPaymentReminderJob;
use App\Models\Card;
use App\Models\Category;
use App\Models\FixedExpense;
use App\Models\PushDevice;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\PushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendPaymentReminderJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'user', 'guard_name' => 'api']);
    }

    public function test_job_is_queueable_and_uses_appropriate_traits(): void
    {
        $job = new SendPaymentReminderJob(
            $this->makeFixedExpense(),
        );

        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
        $this->assertContains(
            \Illuminate\Foundation\Bus\Dispatchable::class,
            class_uses_recursive($job),
        );
        $this->assertContains(
            \Illuminate\Queue\InteractsWithQueue::class,
            class_uses_recursive($job),
        );
        $this->assertContains(
            \Illuminate\Queue\SerializesModels::class,
            class_uses_recursive($job),
        );
    }

    public function test_job_sends_push_notification_to_user_with_active_reminders(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $fixedExpense = $this->makeFixedExpense($user, ['reminders_enabled' => true]);

        (new SendPaymentReminderJob($fixedExpense))->handle();

        Notification::assertSentTo($user, PushNotification::class);
    }

    public function test_job_push_notification_payload_contains_reminder_data(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $fixedExpense = $this->makeFixedExpense(
            $user,
            [
                'description' => 'Internet',
                'amount' => '599.00',
                'reminders_enabled' => true,
                'next_due_date' => '2026-08-15',
            ],
        );

        (new SendPaymentReminderJob($fixedExpense))->handle();

        Notification::assertSentTo(
            $user,
            PushNotification::class,
            function (PushNotification $notification) use ($fixedExpense): bool {
                $reflection = new \ReflectionObject($notification);
                $title = $reflection->getProperty('title');
                $title->setAccessible(true);
                $body = $reflection->getProperty('body');
                $body->setAccessible(true);
                $data = $reflection->getProperty('data');
                $data->setAccessible(true);

                $this->assertSame('Pago próximo', $title->getValue($notification));
                $this->assertStringContainsString('Internet', (string) $body->getValue($notification));
                $this->assertStringContainsString('599.00', (string) $body->getValue($notification));
                $this->assertStringContainsString('15/08/2026', (string) $body->getValue($notification));

                $dataValue = $data->getValue($notification);
                $this->assertSame('payment_reminder', $dataValue['type']);
                $this->assertSame($fixedExpense->id, $dataValue['fixed_expense_id']);
                $this->assertSame('2026-08-15', $dataValue['due_date']);

                return true;
            }
        );
    }

    public function test_job_does_not_send_notification_when_reminders_disabled(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $fixedExpense = $this->makeFixedExpense($user, ['reminders_enabled' => false]);

        (new SendPaymentReminderJob($fixedExpense))->handle();

        Notification::assertNothingSent();
    }

    public function test_job_silently_skips_when_related_user_is_soft_deleted(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $fixedExpense = $this->makeFixedExpense($owner, ['reminders_enabled' => true]);

        // Eloquent's SoftDeletes trait causes the `user` relation to return
        // null once the user is soft-deleted. The job's `! $user` defensive
        // branch must kick in and avoid crashing / sending stale notifications.
        $owner->delete();

        (new SendPaymentReminderJob($fixedExpense->fresh()))->handle();

        Notification::assertNothingSent();
    }

    public function test_job_uses_fcm_channel_when_user_has_active_fcm_tokens(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        PushDevice::factory()->create([
            'user_id' => $user->id,
            'notification_permission' => 'granted',
            'revoked_at' => null,
        ]);

        $fixedExpense = $this->makeFixedExpense($user, ['reminders_enabled' => true]);

        (new SendPaymentReminderJob($fixedExpense))->handle();

        Notification::assertSentTo(
            $user,
            PushNotification::class,
            function (PushNotification $notification) use ($user): bool {
                $channels = $notification->via($user);

                return in_array(\NotificationChannels\Fcm\FcmChannel::class, $channels, true);
            }
        );
    }

    public function test_job_can_be_dispatched_via_queue_facade(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $fixedExpense = $this->makeFixedExpense($user, ['reminders_enabled' => true]);

        SendPaymentReminderJob::dispatch($fixedExpense);

        Queue::assertPushed(SendPaymentReminderJob::class, function (SendPaymentReminderJob $job) use ($fixedExpense): bool {
            $reflection = new \ReflectionObject($job);
            $property = $reflection->getProperty('fixedExpense');
            $property->setAccessible(true);

            return $property->getValue($job)->id === $fixedExpense->id;
        });
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeFixedExpense(?User $user = null, array $overrides = []): FixedExpense
    {
        $user ??= User::factory()->create();

        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
        $workspace->members()->attach($user->id, ['role' => 'owner']);

        $category = Category::factory()->forUser($user)->create();
        $category->workspaces()->attach($workspace->id);

        $card = Card::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id]);

        return FixedExpense::factory()->create(array_merge([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'payment_type' => 'card',
            'payment_instrument_id' => $card->id,
            'amount' => '100.00',
            'description' => 'Test expense',
            'frequency' => 'monthly',
            'next_due_date' => now()->addDays(3)->toDateString(),
            'is_active' => true,
            'reminders_enabled' => true,
        ], $overrides));
    }
}
