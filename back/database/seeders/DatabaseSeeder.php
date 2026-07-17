<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PassportSeeder::class,
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            SubscriptionPaymentSeeder::class,
        ]);
    }
}
