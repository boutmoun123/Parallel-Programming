<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['phone' => '0900000000'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password123'),
                'role' => 'admin',
            ]
        );

        User::updateOrCreate(
            ['phone' => '0911111111'],
            [
                'name' => 'Normal User',
                'password' => Hash::make('password123'),
                'role' => 'user',
            ]
        );

        User::updateOrCreate(
            ['phone' => '0922222222'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password123'),
                'role' => 'user',
            ]
        );
    }
}