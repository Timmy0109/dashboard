<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            // Member management
            [
                'key' => 'member.self_register',
                'name' => '成員自助申請',
                'description' => '允許成員使用邀請碼自行申請加入',
                'category' => 'member',
                'is_default' => true,
            ],
            [
                'key' => 'member.invite_only',
                'name' => '僅限邀請制',
                'description' => '只允許 Manager 主動新增成員',
                'category' => 'member',
                'is_default' => false,
            ],
            [
                'key' => 'member.approval_required',
                'name' => '成員審核機制',
                'description' => '成員申請須經 Manager 審核後才能啟用',
                'category' => 'member',
                'is_default' => true,
            ],

            // Project management
            [
                'key' => 'project.gantt_chart',
                'name' => '甘特圖',
                'description' => '在專案詳細頁顯示甘特圖',
                'category' => 'project',
                'is_default' => true,
            ],
            [
                'key' => 'project.member_assign',
                'name' => '任務指派',
                'description' => '允許在任務上指派負責人',
                'category' => 'project',
                'is_default' => true,
            ],

            // Report
            [
                'key' => 'report.stats_dashboard',
                'name' => '統計分析儀表板',
                'description' => '顯示專案狀態圓餅圖與進度長條圖',
                'category' => 'report',
                'is_default' => true,
            ],
            [
                'key' => 'report.export',
                'name' => '匯出報表',
                'description' => '允許匯出任務與專案報表',
                'category' => 'report',
                'is_default' => false,
            ],

            // System
            [
                'key' => 'system.custom_status',
                'name' => '自訂任務狀態',
                'description' => '允許 Manager 新增或修改任務狀態',
                'category' => 'system',
                'is_default' => true,
            ],
            [
                'key' => 'system.custom_priority',
                'name' => '自訂優先級',
                'description' => '允許 Manager 新增或修改任務優先級',
                'category' => 'system',
                'is_default' => true,
            ],
        ];

        foreach ($features as $f) {
            Feature::updateOrCreate(['key' => $f['key']], $f);
        }
    }
}
