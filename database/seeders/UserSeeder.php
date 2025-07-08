<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin user
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'phone' => '+201234567890',
            'preferred_locale' => 'en',
            'is_admin' => true,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        // Customer users
        User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
            'phone' => '+201234567891',
            'preferred_locale' => 'en',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        User::create([
            'name' => 'أحمد محمد',
            'email' => 'ahmed@example.com',
            'password' => Hash::make('password'),
            'phone' => '+201234567892',
            'preferred_locale' => 'ar',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
