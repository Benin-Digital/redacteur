<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'Abdoul.wakilou-tiga@benin-digital.com'],
            [
                'name' => 'Abdoul',
                'firstname' => 'TIGA',
                'email' => 'Abdoul.wakilou-tiga@benin-digital.com',
                'password' => Hash::make('Benin@Digital#2026'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );
    }
}