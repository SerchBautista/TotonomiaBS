<?php

namespace App\Notifications;

use App\Models\Workspace;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceMemberRemovedNotification extends Notification
{
    public function __construct(
        private readonly Workspace $workspace,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Te han eliminado de un workspace')
            ->line("Has sido eliminado del workspace **{$this->workspace->name}**.")
            ->line('Ya no tendrás acceso a los gastos ni al contenido de este workspace.')
            ->line('Si crees que esto es un error, contacta al propietario del workspace.');
    }
}
