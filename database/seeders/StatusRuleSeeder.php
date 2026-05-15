<?php

namespace Database\Seeders;

use App\Models\StatusRule;
use Illuminate\Database\Seeder;

class StatusRuleSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => '準備中', 'icon' => '⚪', 'color' => '#6b7280', 'sort_order' => 1],
            ['name' => '進行中', 'icon' => '🔵', 'color' => '#3b82f6', 'sort_order' => 2],
            ['name' => '待確認', 'icon' => '🟡', 'color' => '#f59e0b', 'sort_order' => 3],
            ['name' => '追蹤中', 'icon' => '🟠', 'color' => '#f97316', 'sort_order' => 4],
            ['name' => '已完成', 'icon' => '🟢', 'color' => '#10b981', 'sort_order' => 5],
        ];

        foreach ($statuses as $status) {
            StatusRule::create($status);
        }
    }
}
