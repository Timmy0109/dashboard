<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Priority;
use App\Models\Project;
use App\Models\StatusRule;
use App\Models\Task;
use App\Models\TaskFee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 財務總覽（/api/finance/overview）
 * 公司範圍唯讀：僅費用流程參與者（老闆 / 會計）；admin 不參與費用、member 不可見
 */
class FinanceOverviewTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $boss;
    private User $accountant;
    private Project $project;
    private Category $category;
    private Priority $priority;
    private StatusRule $status;

    protected function setUp(): void
    {
        parent::setUp();

        $this->status   = StatusRule::create(['name' => '進行中', 'icon' => '▶', 'color' => '#3b82f6', 'sort_order' => 1, 'is_active' => true]);
        $this->priority = Priority::create(['name' => '中', 'color' => '#f59e0b', 'sort_order' => 2, 'is_active' => true]);
        $this->category = Category::create(['name' => '開發', 'color' => '#6366f1', 'is_active' => true]);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->company = Company::create([
            'name' => 'FinCo', 'status' => 'active',
            'invite_code' => 'FINCO1234567', 'created_by' => $admin->id,
        ]);

        $this->boss = User::factory()->create([
            'role' => 'boss', 'status' => 'active', 'company_id' => $this->company->id,
        ]);
        $this->accountant = User::factory()->create([
            'role' => 'accountant', 'status' => 'active', 'company_id' => $this->company->id,
        ]);

        $this->project = $this->makeProject('財務測試專案', $this->company, $this->boss, 100000);
    }

    private function makeProject(string $name, Company $company, User $owner, float $budget): Project
    {
        return Project::create([
            'name'         => $name,
            'category_id'  => $this->category->id,
            'priority_id'  => $this->priority->id,
            'status_id'    => $this->status->id,
            'start_date'   => now(),
            'owner_id'     => $owner->id,
            'created_by'   => $owner->id,
            'company_id'   => $company->id,
            'total_budget' => $budget,
        ]);
    }

    private function makeFee(string $status, float $amount): TaskFee
    {
        $member = User::factory()->create([
            'role' => 'member', 'status' => 'active', 'company_id' => $this->company->id,
        ]);
        $task = Task::create([
            'project_id' => $this->project->id, 'name' => '任務', 'assignee_id' => $member->id,
            'start_date' => now(), 'end_date' => now()->addDay(),
            'status_id' => $this->status->id, 'priority_id' => $this->priority->id,
            'created_by' => $this->boss->id,
        ]);

        return TaskFee::create([
            'task_id' => $task->id, 'project_id' => $this->project->id,
            'submitted_by' => $member->id, 'amount' => $amount, 'status' => $status,
        ]);
    }

    public function test_accountant_sees_company_overview_with_correct_sums(): void
    {
        $this->makeFee('disbursed', 1000);
        $this->makeFee('pending', 300);
        $this->makeFee('reviewed', 200);
        $this->makeFee('rejected', 9999); // 不計入
        $this->project->adminFees()->create([
            'amount' => 500, 'created_by' => $this->boss->id, 'incurred_on' => now(),
        ]);

        $res = $this->actingAs($this->accountant)
            ->getJson('/api/finance/overview')
            ->assertOk()
            ->json();

        $row = collect($res['projects'])->firstWhere('id', $this->project->id);
        $this->assertEquals(100000.0, $row['total_budget']);
        $this->assertEquals(1000.0, $row['task_disbursed']);
        $this->assertEquals(500.0, $row['task_in_flight']);   // pending 300 + reviewed 200
        $this->assertEquals(500.0, $row['admin_total']);
        $this->assertEquals(1500.0, $row['spent']);            // 核發 1000 + 行政 500
        $this->assertEquals(98500.0, $row['remaining']);
        $this->assertEquals(98500.0, $res['totals']['remaining']);
    }

    public function test_other_company_projects_are_excluded(): void
    {
        $otherAdmin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $otherCompany = Company::create([
            'name' => 'OtherCo', 'status' => 'active',
            'invite_code' => 'OTHER1234567', 'created_by' => $otherAdmin->id,
        ]);
        $otherBoss = User::factory()->create([
            'role' => 'boss', 'status' => 'active', 'company_id' => $otherCompany->id,
        ]);
        $this->makeProject('他公司專案', $otherCompany, $otherBoss, 5);

        $res = $this->actingAs($this->accountant)
            ->getJson('/api/finance/overview')
            ->assertOk()
            ->json();

        $this->assertCount(1, $res['projects']);
        $this->assertSame($this->project->id, $res['projects'][0]['id']);
    }

    public function test_boss_can_access_overview(): void
    {
        $this->actingAs($this->boss)
            ->getJson('/api/finance/overview')
            ->assertOk();
    }

    public function test_member_and_admin_cannot_access_overview(): void
    {
        $member = User::factory()->create([
            'role' => 'member', 'status' => 'active', 'company_id' => $this->company->id,
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->actingAs($member)->getJson('/api/finance/overview')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/finance/overview')->assertForbidden();
    }
}
