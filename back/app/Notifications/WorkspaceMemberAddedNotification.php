<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceMemberAddedNotification extends Notification
{
    public function __construct(
        private readonly Workspace $workspace,
        private readonly User $addedBy,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        return (new MailMessage)
            ->subject("Te invitaron al workspace \"{$this->workspace->name}\"")
            ->line("**{$this->addedBy->name}** te ha agregado al workspace:")
            ->line("# {$this->workspace->name}")
            ->line('Ya tienes acceso para gestionar los gastos de este workspace.')
            ->action('Ir a la aplicación', $frontendUrl)
            ->line('Si no esperabas esta invitación, puedes ignorar este correo.');
    }
}
