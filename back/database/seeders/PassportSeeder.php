<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

class PassportSeeder extends Seeder
{
    public function run(): void
    {
        $clientRepository = app(ClientRepository::class);

        $personalClient = Client::query()
            ->where('provider', 'users')
            ->whereJsonContains('grant_types', 'personal_access')
            ->where('revoked', false)
            ->first();

        if (! $personalClient) {
            $clientRepository->createPersonalAccessGrantClient(
                'Template Personal Access Client',
                'users'
            );
        }

        $passwordClient = Client::query()
            ->where('provider', 'users')
            ->whereJsonContains('grant_types', 'password')
            ->where('revoked', false)
            ->first();

        if (! $passwordClient) {
            $clientRepository->createPasswordGrantClient(
                'Password Grant Client',
                'users',
                false
            );
        }
    }
}
