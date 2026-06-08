<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyFeature;
use App\Models\Feature;
use App\Models\Priority;
use App\Models\Project;
use App\Models\ProjectAdminFee;
use App\Models\ProjectMember;
use App\Models\StatusRule;
use App\Models\Task;
use App\Models\TaskFee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 多公司 demo 資料：5 間公司（前 2 間只有 boss、無會計；後 3 間全角色），
 * 每間 5-10 員工、3-5 專案、每專案 7-10 任務（狀態隨機）、費用隨機分布。
 *
 * 每間公司各自的 boss（不同名）；密碼一律 password。
 * 登入帳號：boss1..5@demo.test、acc3..5@demo.test、m{n}@demo.test
 */
class DemoMultiCompanySeeder extends Seeder
{
    private array $usedNames = [];
    private int $memberSeq = 0;

    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();
        if (! $admin) {
            return;
        }

        $categoryIds = Category::pluck('id')->all();
        $priorityIds = Priority::pluck('id')->all();
        $statuses    = StatusRule::all();
        $jobTitles   = ['業務助理', '專案經理', '美編', '工程師', '行政', '出納'];
        $defaultFeatureKeys = Feature::defaults();
        $allFeatureKeys     = Feature::pluck('key')->all();

        if (empty($categoryIds) || empty($priorityIds) || $statuses->isEmpty()) {
            return; // lookup 未就緒
        }

        $companyPrefix = ['宏達', '鴻昇', '啟元', '明新', '日盛', '禾豐', '長榮', '創世', '群益', '安泰'];
        $companySuffix = ['科技', '實業', '數位', '設計', '行銷'];
        shuffle($companyPrefix);

        for ($c = 1; $c <= 5; $c++) {
            $hasAccountant = $c >= 3; // 前 2 間 boss-only，後 3 間全角色

            // 1) boss（各公司不同名）
            $boss = User::create([
                'name'      => $this->uniqueName() . '（老闆）',
                'email'     => "boss{$c}@demo.test",
                'password'  => Hash::make('password'),
                'role'      => 'boss',
                'job_title' => '老闆',
                'status'    => 'active',
            ]);

            // 2) 公司
            $company = Company::create([
                'name'        => $companyPrefix[$c - 1] . $companySuffix[array_rand($companySuffix)],
                'status'      => 'active',
                'invite_code' => strtoupper(Str::random(12)),
                'created_by'  => $admin->id,
            ]);
            $boss->update(['company_id' => $company->id]);

            // 公司功能：預設啟用、其餘停用
            foreach ($defaultFeatureKeys as $key) {
                CompanyFeature::create([
                    'company_id' => $company->id, 'feature_key' => $key,
                    'enabled' => true, 'enabled_by' => $admin->id, 'enabled_at' => now(),
                ]);
            }
            foreach (array_diff($allFeatureKeys, $defaultFeatureKeys) as $key) {
                CompanyFeature::create([
                    'company_id' => $company->id, 'feature_key' => $key, 'enabled' => false,
                ]);
            }

            // 3) 會計（後 3 間才有）
            $accountant = null;
            if ($hasAccountant) {
                $accountant = User::create([
                    'name'      => $this->uniqueName() . '（會計）',
                    'email'     => "acc{$c}@demo.test",
                    'password'  => Hash::make('password'),
                    'role'      => 'accountant',
                    'job_title' => '會計',
                    'company_id' => $company->id,
                    'status'    => 'active',
                ]);
            }

            // 4) 成員（湊到 5-10 員工）
            $totalEmployees = rand(5, 10);
            $memberCount = max(2, $totalEmployees - 1 - ($hasAccountant ? 1 : 0));
            $members = [];
            for ($m = 0; $m < $memberCount; $m++) {
                $this->memberSeq++;
                $members[] = User::create([
                    'name'       => $this->uniqueName(),
                    'email'      => "m{$this->memberSeq}@demo.test",
                    'password'   => Hash::make('password'),
                    'role'       => 'member',
                    'job_title'  => $jobTitles[array_rand($jobTitles)],
                    'company_id' => $company->id,
                    'status'     => 'active',
                ]);
            }

            $reviewer  = $accountant ?? $boss; // 一階審核者
            $feeNotesT = ['客戶訪談車資', '設計外包費', '素材授權', '行動上網費', '快遞運費', '加班餐費', '軟體訂閱', '印刷輸出'];
            $feeNotesA = ['伺服器年費', '辦公文具', '雲端儲存', '網域續約', '會計師費用', '辦公室水電'];

            // 5) 專案 3-5 個
            $projectCount = rand(3, 5);
            for ($p = 1; $p <= $projectCount; $p++) {
                $status = $statuses->random();
                $project = Project::create([
                    'project_no'   => "P{$c}-" . str_pad((string) $p, 3, '0', STR_PAD_LEFT),
                    'name'         => $this->projectName($p),
                    'note'         => 'Demo 多公司資料',
                    'category_id'  => $categoryIds[array_rand($categoryIds)],
                    'company_id'   => $company->id,
                    'owner_id'     => $boss->id,
                    'priority_id'  => $priorityIds[array_rand($priorityIds)],
                    'status_id'    => $status->id,
                    'start_date'   => now()->subDays(rand(20, 90)),
                    'due_date'     => now()->addDays(rand(20, 120)),
                    'total_budget' => rand(3, 15) * 100000,
                    'created_by'   => $boss->id,
                ]);

                // 專案成員：boss(owner) + 隨機成員
                ProjectMember::create(['project_id' => $project->id, 'user_id' => $boss->id, 'role' => 'owner']);
                $picked = collect($members)->shuffle()->take(rand(2, max(2, count($members))))->values();
                foreach ($picked as $mem) {
                    ProjectMember::create(['project_id' => $project->id, 'user_id' => $mem->id, 'role' => 'member']);
                }
                $assignables = $picked->isEmpty() ? collect([$boss]) : $picked;

                // 6) 任務 7-10 個，狀態隨機
                $taskCount = rand(7, 10);
                for ($t = 1; $t <= $taskCount; $t++) {
                    $st = $statuses->random();
                    [$progress, $done] = $this->progressFor($st->name);
                    Task::create([
                        'project_id'  => $project->id,
                        'name'        => $this->taskName($t),
                        'start_date'  => now()->subDays(rand(5, 60)),
                        'end_date'    => now()->addDays(rand(5, 60)),
                        'progress'    => $progress,
                        'status_id'   => $st->id,
                        'priority_id' => $priorityIds[array_rand($priorityIds)],
                        'assignee_id' => $assignables->random()->id,
                        'is_completed' => $done,
                        'completed_at' => $done ? now()->subDays(rand(1, 20)) : null,
                        'created_by'  => $boss->id,
                    ]);
                }
                $projectTasks = Task::where('project_id', $project->id)->pluck('id')->all();

                // 7) 任務費用：隨機 2-6 筆，狀態隨機分布
                $feeCount = rand(2, 6);
                for ($f = 0; $f < $feeCount; $f++) {
                    $submitter = $assignables->random();
                    $state = ['pending', 'pending', 'reviewed', 'disbursed', 'disbursed', 'rejected'][array_rand([0,1,2,3,4,5])];
                    $fee = [
                        'task_id'      => $projectTasks[array_rand($projectTasks)],
                        'project_id'   => $project->id,
                        'submitted_by' => $submitter->id,
                        'amount'       => rand(5, 400) * 100,
                        'note'         => $feeNotesT[array_rand($feeNotesT)],
                        'status'       => $state,
                    ];
                    if ($state === 'reviewed') {
                        $fee['reviewed_by'] = $reviewer->id;
                        $fee['reviewed_at'] = now()->subDays(rand(1, 8));
                    } elseif ($state === 'disbursed') {
                        $fee['reviewed_by'] = $reviewer->id;
                        $fee['reviewed_at'] = now()->subDays(rand(6, 12));
                        $fee['disbursed_by'] = $boss->id;
                        $fee['disbursed_at'] = now()->subDays(rand(1, 5));
                    } elseif ($state === 'rejected') {
                        $fee['reviewed_by'] = $reviewer->id;
                        $fee['reviewed_at'] = now()->subDays(rand(1, 8));
                        $fee['reject_reason'] = '憑證不齊，請補正後重送';
                    }
                    TaskFee::create($fee);
                }

                // 8) 行政費用 1-3 筆（boss 登錄）
                $adminFeeCount = rand(1, 3);
                for ($a = 0; $a < $adminFeeCount; $a++) {
                    ProjectAdminFee::create([
                        'project_id'  => $project->id,
                        'created_by'  => $boss->id,
                        'amount'      => rand(50, 600) * 100,
                        'note'        => $feeNotesA[array_rand($feeNotesA)],
                        'incurred_on' => now()->subDays(rand(1, 40))->toDateString(),
                    ]);
                }
            }
        }
    }

    private function uniqueName(): string
    {
        $surnames = ['王', '林', '陳', '張', '李', '黃', '吳', '劉', '蔡', '楊', '許', '鄭', '謝', '洪', '曾', '周', '葉', '蘇'];
        $given    = ['志明', '淑芬', '家豪', '怡君', '俊傑', '雅婷', '建宏', '美玲', '宗翰', '心怡', '冠廷', '詩涵', '承翰', '婉婷', '柏翰', '佳穎', '宇軒', '思妤', '冠宇', '雅雯'];
        do {
            $name = $surnames[array_rand($surnames)] . $given[array_rand($given)];
        } while (in_array($name, $this->usedNames, true));
        $this->usedNames[] = $name;
        return $name;
    }

    private function projectName(int $i): string
    {
        $pool = ['官網改版', '品牌識別重塑', '電商平台建置', 'APP 開發', 'CRM 導入', '年度行銷活動', '展場規劃', '內部系統整合', '資料中台', '會員經營'];
        return $pool[($i - 1) % count($pool)] . '專案';
    }

    private function taskName(int $i): string
    {
        $pool = ['需求訪談', '規格確認', 'UI 設計', '前端開發', '後端 API', '資料庫設計', '串接測試', 'QA 驗收', '上線部署', '教育訓練', '文件撰寫', '成效追蹤'];
        return $pool[($i - 1) % count($pool)];
    }

    /** @return array{0:int,1:bool} [progress, is_completed] */
    private function progressFor(string $statusName): array
    {
        if (str_contains($statusName, '完成')) {
            return [100, true];
        }
        if (str_contains($statusName, '準備') || str_contains($statusName, '待')) {
            return [0, false];
        }
        return [rand(10, 90), false];
    }
}
