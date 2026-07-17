<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use SensitiveParameter;

class TwoFactorCodeNotification extends Notification
{
    public function __construct(#[SensitiveParameter] private readonly string $code) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('api.notifications.two_factor.subject'))
            ->line(__('api.notifications.two_factor.code_line'))
            ->line('**'.$this->code.'**')
            ->line(__('api.notifications.two_factor.expiry_line', ['minutes' => config('two-factor.code_expiry_minutes', 5)]))
            ->line(__('api.notifications.two_factor.ignore_line'));
    }
}
