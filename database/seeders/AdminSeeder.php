<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'المدير العام',
            'email' => 'amon542321@gmail.com',
            'phone' => '0500000000',
            'password' => Hash::make('12345678'),
            'role' => 'admin',
            'status' => 'active',
        ]);
    }
}
