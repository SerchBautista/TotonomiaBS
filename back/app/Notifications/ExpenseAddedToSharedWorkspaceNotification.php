<?php

namespace App\Notifications;

use App\Models\Expense;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class ExpenseAddedToSharedWorkspaceNotification extends Notification
{
    use Queueable;

    public const TYPE = 'expense_added_to_shared_workspace';

    public function __construct(
        private readonly Expense $expense,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->notifiableHasActiveFcmTokens($notifiable)) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => self::TYPE,
            'title' => __('api.notifications.expense_added_to_shared_workspace.title', [
                'user_name' => $this->expense->user?->name ?? '',
                'workspace_name' => $this->expense->workspace?->name ?? '',
            ]),
            'description' => $this->expense->description,
            'amount' => $this->expense->amount,
            'expense_id' => $this->expense->id,
            'workspace_id' => $this->expense->workspace_id,
            'user_id' => $this->expense->user_id,
        ];
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        return (new FcmMessage(notification: new FcmNotification(
            title: __('api.notifications.expense_added_to_shared_workspace.title', [
                'user_name' => $this->expense->user?->name ?? '',
                'workspace_name' => $this->expense->workspace?->name ?? '',
            ]),
            body: (string) ($this->expense->description ?? ''),
        )))->data([
            'type' => self::TYPE,
            'expense_id' => $this->expense->id,
            'workspace_id' => $this->expense->workspace_id,
            'user_id' => $this->expense->user_id,
        ]);
    }

    private function notifiableHasActiveFcmTokens(object $notifiable): bool
    {
        if (! method_exists($notifiable, 'routeNotificationForFcm')) {
            return false;
        }

        $tokens = $notifiable->routeNotificationForFcm();

        return is_array($tokens) && count($tokens) > 0;
    }
}
