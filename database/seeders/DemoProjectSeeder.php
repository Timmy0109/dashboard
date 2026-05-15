<?php

namespace Database\Seeders;

use App\Models\Priority;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\StatusRule;
use App\Models\Task;
use App\Models\User;
use App\Models\Category;
use Illuminate\Database\Seeder;

class DemoProjectSeeder extends Seeder
{
    public function run(): void
    {
        $manager = User::where('email', 'manager@demo.com')->first();
        $member = User::where('email', 'member@demo.com')->first();
        $category = Category::where('name', '網站建置')->first();
        $highPriority = Priority::where('name', '高')->first();
        $midPriority = Priority::where('name', '中')->first();
        $statusInProgress = StatusRule::where('name', '進行中')->first();
        $statusReady = StatusRule::where('name', '準備中')->first();
        $statusDone = StatusRule::where('name', '已完成')->first();

        $project = Project::create([
            'project_no' => 'P-2026-001',
            'name' => '官網改版專案',
            'note' => 'Demo 用途：公司官網全面改版，包含前後台重構',
            'category_id' => $category->id,
            'owner_id' => $manager->id,
            'priority_id' => $highPriority->id,
            'status_id' => $statusInProgress->id,
            'start_date' => '2026-05-01',
            'due_date' => '2026-07-31',
            'created_by' => $manager->id,
        ]);

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $manager->id,
            'role' => 'owner',
        ]);

        ProjectMember::create([
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        $tasks = [
            [
                'name' => '需求訪談與規格確認',
                'start_date' => '2026-05-01',
                'end_date' => '2026-05-10',
                'progress' => 100,
                'status_id' => $statusDone->id,
                'priority_id' => $highPriority->id,
                'assignee_id' => $manager->id,
                'is_completed' => true,
            ],
            [
                'name' => 'UI/UX 設計稿',
                'start_date' => '2026-05-08',
                'end_date' => '2026-05-25',
                'progress' => 60,
                'status_id' => $statusInProgress->id,
                'priority_id' => $highPriority->id,
                'assignee_id' => $member->id,
                'is_completed' => false,
            ],
            [
                'name' => '前端開發',
                'start_date' => '2026-05-20',
                'end_date' => '2026-06-30',
                'progress' => 20,
                'status_id' => $statusInProgress->id,
                'priority_id' => $midPriority->id,
                'assignee_id' => $member->id,
                'is_completed' => false,
            ],
            [
                'name' => '後端 API 開發',
                'start_date' => '2026-05-15',
                'end_date' => '2026-06-20',
                'progress' => 30,
                'status_id' => $statusInProgress->id,
                'priority_id' => $highPriority->id,
                'assignee_id' => $manager->id,
                'is_completed' => false,
            ],
            [
                'name' => 'QA 測試',
                'start_date' => '2026-07-01',
                'end_date' => '2026-07-20',
                'progress' => 0,
                'status_id' => $statusReady->id,
                'priority_id' => $midPriority->id,
                'assignee_id' => $member->id,
                'is_completed' => false,
            ],
        ];

        foreach ($tasks as $taskData) {
            Task::create(array_merge($taskData, [
                'project_id' => $project->id,
                'created_by' => $manager->id,
            ]));
        }
    }
}
