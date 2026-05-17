<?php

namespace Database\Seeders;

use App\Models\StatusRule;
use Illuminate\Database\Seeder;

class StatusRuleSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => '準備中', 'icon' => 'radiobox-blank',      'color' => '#6b7280', 'sort_order' => 1],
            ['name' => '進行中', 'icon' => 'progress-clock',       'color' => '#3b82f6', 'sort_order' => 2],
            ['name' => '待確認', 'icon' => 'help-circle-outline',  'color' => '#f59e0b', 'sort_order' => 3],
            ['name' => '追蹤中', 'icon' => 'eye-outline',          'color' => '#f97316', 'sort_order' => 4],
            ['name' => '已完成', 'icon' => 'check-circle-outline', 'color' => '#10b981', 'sort_order' => 5],
        ];

        foreach ($statuses as $status) {
            StatusRule::create($status);
        }
    }
}
