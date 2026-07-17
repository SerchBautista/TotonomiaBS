<?php

namespace App\Jobs;

use App\Models\FixedExpense;
use App\Notifications\PushNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPaymentReminderJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly FixedExpense $fixedExpense,
    ) {}

    public function handle(): void
    {
        $user = $this->fixedExpense->user;

        if (! $user || ! $this->fixedExpense->reminders_enabled) {
            return;
        }

        $user->notify(new PushNotification(
            title: 'Pago próximo',
            body: sprintf(
                '%s — $%s vence el %s',
                $this->fixedExpense->description,
                number_format((float) $this->fixedExpense->amount, 2),
                $this->fixedExpense->next_due_date->format('d/m/Y')
            ),
            data: [
                'type' => 'payment_reminder',
                'fixed_expense_id' => $this->fixedExpense->id,
                'due_date' => $this->fixedExpense->next_due_date->toDateString(),
            ]
        ));
    }
}
