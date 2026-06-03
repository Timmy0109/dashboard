<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\ProjectAdminFee;
use App\Models\Task;
use App\Models\TaskFee;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoFeeSeeder extends Seeder
{
    /**
     * Demo 費用資料 — 涵蓋 3 階段流程的四種狀態，
     * 讓費用審核頁（會計 / 老闆）、費用彙總、預算警示都有東西可看。
     *
     * 流程：member 提交 → accountant 審核(reviewed) → boss 核發(disbursed)
     */
    public function run(): void
    {
        $boss       = User::where('email', 'boss@demo.com')->first();
        $accountant = User::where('email', 'accountant@demo.com')->first();
        $member     = User::where('email', 'member@demo.com')->first();
        $project    = Project::where('project_no', 'P-2026-001')->first();

        if (! $project || ! $member || ! $accountant || ! $boss) {
            return; // demo 基礎資料不存在就跳過
        }

        $tasks = Task::where('project_id', $project->id)->orderBy('id')->get();
        if ($tasks->isEmpty()) {
            return;
        }
        $taskFor = fn (int $i) => $tasks[$i % $tasks->count()]->id;

        // 1) pending — member 剛提交，等會計審核
        TaskFee::create([
            'task_id'      => $taskFor(1),
            'project_id'   => $project->id,
            'submitted_by' => $member->id,
            'amount'       => 3500,
            'note'         => '客戶訪談來回計程車費',
            'status'       => TaskFee::STATUS_PENDING,
        ]);

        // 2) pending + 已被要求補件
        TaskFee::create([
            'task_id'              => $taskFor(2),
            'project_id'           => $project->id,
            'submitted_by'         => $member->id,
            'amount'               => 2000,
            'note'                 => '素材試用授權（小額）',
            'status'               => TaskFee::STATUS_PENDING,
            'receipt_requested_at' => now()->subDays(1),
            'receipt_requested_by' => $accountant->id,
            'receipt_request_message' => '請補上正式收據，謝謝',
        ]);

        // 3) reviewed — 會計已一階審核，等老闆核發
        TaskFee::create([
            'task_id'      => $taskFor(1),
            'project_id'   => $project->id,
            'submitted_by' => $member->id,
            'amount'       => 8000,
            'note'         => 'UI 設計外包費',
            'status'       => TaskFee::STATUS_REVIEWED,
            'reviewed_by'  => $accountant->id,
            'reviewed_at'  => now()->subDays(2),
        ]);

        // 4) disbursed — 二階核發完成（已撥付）
        TaskFee::create([
            'task_id'      => $taskFor(3),
            'project_id'   => $project->id,
            'submitted_by' => $member->id,
            'amount'       => 12000,
            'note'         => '圖庫素材年度授權',
            'status'       => TaskFee::STATUS_DISBURSED,
            'reviewed_by'  => $accountant->id,
            'reviewed_at'  => now()->subDays(5),
            'disbursed_by' => $boss->id,
            'disbursed_at' => now()->subDays(4),
        ]);

        // 5) rejected — 會計退件
        TaskFee::create([
            'task_id'       => $taskFor(2),
            'project_id'    => $project->id,
            'submitted_by'  => $member->id,
            'amount'        => 5000,
            'note'          => '個人加班餐費',
            'status'        => TaskFee::STATUS_REJECTED,
            'reviewed_by'   => $accountant->id,
            'reviewed_at'   => now()->subDays(3),
            'reject_reason' => '非專案直接相關支出，請走公司行政流程',
        ]);

        // 行政費用（老闆 / 會計 直接登錄，免審核）
        ProjectAdminFee::create([
            'project_id'  => $project->id,
            'created_by'  => $boss->id,
            'amount'      => 25000,
            'note'        => '正式環境伺服器年費',
            'incurred_on' => now()->subDays(10)->toDateString(),
        ]);

        ProjectAdminFee::create([
            'project_id'  => $project->id,
            'created_by'  => $accountant->id,
            'amount'      => 8000,
            'note'        => '專案相關辦公文具與耗材',
            'incurred_on' => now()->subDays(6)->toDateString(),
        ]);
    }
}
