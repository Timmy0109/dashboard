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
            JobTitleSeeder::class,
            FeatureSeeder::class,
            UserSeeder::class,
            CompanySeeder::class,
            DemoProjectSeeder::class,
            DemoFeeSeeder::class,
        ]);
    }
}
