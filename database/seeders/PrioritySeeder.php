<?php

namespace Database\Seeders;

use App\Models\Priority;
use Illuminate\Database\Seeder;

class PrioritySeeder extends Seeder
{
    public function run(): void
    {
        $priorities = [
            ['name' => '高', 'color' => '#ef4444', 'sort_order' => 1],
            ['name' => '中', 'color' => '#f59e0b', 'sort_order' => 2],
            ['name' => '低', 'color' => '#6b7280', 'sort_order' => 3],
        ];

        foreach ($priorities as $priority) {
            Priority::create($priority);
        }
    }
}
