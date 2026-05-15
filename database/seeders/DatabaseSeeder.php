<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            PrioritySeeder::class,
            StatusRuleSeeder::class,
            UserSeeder::class,
            DemoProjectSeeder::class,
        ]);
    }
}
