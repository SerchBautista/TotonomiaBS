<?php

namespace App\Notifications;

use App\Models\FixedExpense;
use App\Models\FixedExpenseOccurrence;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FixedExpenseEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly FixedExpense $fixedExpense,
        private readonly FixedExpenseOccurrence $occurrence,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $amount = number_format((float) $this->fixedExpense->amount, 2);
        $dueDate = $this->occurrence->due_date->format('d/m/Y');
        $description = $this->fixedExpense->description;
        $workspaceName = $this->fixedExpense->workspace->name;

        return (new MailMessage)
            ->subject("Pago próximo: {$description}")
            ->line("Tienes un pago próximo en el workspace **{$workspaceName}**:")
            ->line("**{$description}**")
            ->line("Monto: **\${$amount}**")
            ->line("Vence: **{$dueDate}**")
            ->action('Ver en la app', $frontendUrl)
            ->line('Si ya realizaste el pago, puedes ignorar este mensaje.');
    }
}
