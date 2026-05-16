<?php

namespace Database\Seeders;

use App\Models\StatusRule;
use Illuminate\Database\Seeder;

class StatusRuleSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => '準備中', 'icon' => 'radio_button_unchecked', 'color' => '#6b7280', 'sort_order' => 1],
            ['name' => '進行中', 'icon' => 'pending', 'color' => '#3b82f6', 'sort_order' => 2],
            ['name' => '待確認', 'icon' => 'help_outline', 'color' => '#f59e0b', 'sort_order' => 3],
            ['name' => '追蹤中', 'icon' => 'visibility', 'color' => '#f97316', 'sort_order' => 4],
            ['name' => '已完成', 'icon' => 'check_circle', 'color' => '#10b981', 'sort_order' => 5],
        ];

        foreach ($statuses as $status) {
            StatusRule::create($status);
        }
    }
}
