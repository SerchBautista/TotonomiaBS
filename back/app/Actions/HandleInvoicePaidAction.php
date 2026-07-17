<?php

namespace App\Actions;

use App\Contracts\HandleInvoicePaidActionInterface;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class HandleInvoicePaidAction implements HandleInvoicePaidActionInterface
{
    public function execute(object $invoice): void
    {
        $customerId = $invoice->customer ?? null;

        if ($customerId === null || ($invoice->amount_paid ?? 0) === 0) {
            return;
        }

        $user = User::where('stripe_customer_id', $customerId)->first();

        if ($user === null) {
            return;
        }

        $alreadyRecorded = $user->subscriptionPayments()
            ->where('gateway_payment_id', $invoice->id)
            ->exists();

        if ($alreadyRecorded) {
            return;
        }

        SubscriptionPayment::create([
            'user_id' => $user->id,
            'amount' => $invoice->amount_paid / 100,
            'currency' => strtoupper($invoice->currency),
            'status' => 'paid',
            'gateway' => 'stripe',
            'gateway_payment_id' => $invoice->id,
            'invoice_url' => $invoice->hosted_invoice_url ?? null,
            'paid_at' => Carbon::createFromTimestamp($invoice->created),
        ]);

        Log::info('Stripe invoice recorded.', ['user_id' => $user->id, 'invoice_id' => $invoice->id]);
    }
}
