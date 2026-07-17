<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetNotification extends Notification
{
    public function __construct(private readonly string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $email = $notifiable->getEmailForPasswordReset();

        $url = $frontendUrl.'/user/reset-password?'
            .http_build_query(['token' => $this->token, 'email' => $email]);

        return (new MailMessage)
            ->subject('Restablece tu contraseña')
            ->line('Recibiste este correo porque se solicitó un restablecimiento de contraseña para tu cuenta.')
            ->action('Restablecer contraseña', $url)
            ->line('Este enlace expirará en 60 minutos.')
            ->line('Si no solicitaste un restablecimiento de contraseña, no es necesario que hagas nada.');
    }
}
