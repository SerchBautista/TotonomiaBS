<?php

namespace Database\Seeders;

use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Database\Seeder;

class SubscriptionPaymentSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'user@example.com')->first();

        if (! $user) {
            return;
        }

        $payments = [
            [
                'amount' => 0.00,
                'currency' => 'USD',
                'status' => 'paid',
                'gateway' => 'dummy',
                'gateway_payment_id' => null,
                'invoice_url' => null,
                'paid_at' => now()->subMonths(3),
            ],
            [
                'amount' => 9.99,
                'currency' => 'USD',
                'status' => 'paid',
                'gateway' => 'dummy',
                'gateway_payment_id' => null,
                'invoice_url' => null,
                'paid_at' => now()->subMonths(2),
            ],
            [
                'amount' => 9.99,
                'currency' => 'USD',
                'status' => 'paid',
                'gateway' => 'stripe',
                'gateway_payment_id' => 'in_test_1234567890',
                'invoice_url' => null,
                'paid_at' => now()->subMonth(),
            ],
            [
                'amount' => 9.99,
                'currency' => 'USD',
                'status' => 'paid',
                'gateway' => 'stripe',
                'gateway_payment_id' => 'in_test_0987654321',
                'invoice_url' => null,
                'paid_at' => now(),
            ],
        ];

        foreach ($payments as $data) {
            SubscriptionPayment::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'gateway_payment_id' => $data['gateway_payment_id'],
                    'paid_at' => $data['paid_at'],
                ],
                $data + ['user_id' => $user->id],
            );
        }
    }
}
