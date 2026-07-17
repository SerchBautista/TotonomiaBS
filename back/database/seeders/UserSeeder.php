<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Template Admin',
                'password' => Hash::make('StrongPass123'),
                'email_verified_at' => now(),
            ]
        );

        $regular = User::query()->updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Template User',
                'password' => Hash::make('StrongPass123'),
                'email_verified_at' => now(),
            ]
        );

        $admin->syncRoles(['admin']);
        $regular->syncRoles(['user']);
    }
}
