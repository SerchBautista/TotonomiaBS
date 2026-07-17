<?php

namespace App\Notifications;

use App\Models\FixedExpense;
use App\Models\FixedExpenseOccurrence;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class FixedExpenseProcessedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly FixedExpense $fixedExpense,
        private readonly FixedExpenseOccurrence $occurrence,
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
            'type' => 'fixed_expense_processed',
            'title' => $this->fixedExpense->description,
            'amount' => $this->fixedExpense->amount,
            'due_date' => $this->occurrence->due_date->toDateString(),
            'fixed_expense_id' => $this->fixedExpense->id,
            'occurrence_id' => $this->occurrence->id,
            'workspace_id' => $this->fixedExpense->workspace_id,
        ];
    }

    public function toFcm(object $notifiable): FcmMessage
    {
        return (new FcmMessage(notification: new FcmNotification(
            title: __('Gasto fijo procesado'),
            body: __(':description por :amount', [
                'description' => $this->fixedExpense->description,
                'amount' => number_format($this->fixedExpense->amount, 2),
            ]),
        )))->data([
            'type' => 'fixed_expense_processed',
            'fixed_expense_id' => $this->fixedExpense->id,
            'occurrence_id' => $this->occurrence->id,
            'workspace_id' => $this->fixedExpense->workspace_id,
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
