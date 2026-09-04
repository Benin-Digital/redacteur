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
            ['email' => 'votre-email@gmail.com'],
            [
                'name' => 'Nom',
                'firstname' => 'Prénom',
                'email' => 'votre-email@gmail.com',
                'password' => Hash::make('MdpFortBeninDigital12345678!'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );
    }
}