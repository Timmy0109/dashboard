<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@demo.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        User::create([
            'name' => '王老闆',
            'email' => 'boss@demo.com',
            'password' => Hash::make('password'),
            'role' => 'boss',
            'job_title' => '老闆',
            'status' => 'active',
        ]);

        User::create([
            'name' => '陳會計',
            'email' => 'accountant@demo.com',
            'password' => Hash::make('password'),
            'role' => 'accountant',
            'job_title' => '會計',
            'status' => 'active',
        ]);

        User::create([
            'name' => '李小明',
            'email' => 'member@demo.com',
            'password' => Hash::make('password'),
            'role' => 'member',
            'job_title' => '業務助理',
            'status' => 'active',
        ]);
    }
}
