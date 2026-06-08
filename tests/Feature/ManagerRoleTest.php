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
 * 專案經理（manager）角色：
 *  - 可建立專案（owner 即本人）、管自己的專案
 *  - 不可管別人的專案
 *  - 可提交費用（自己被指派的任務 / 自己專案的任務）
 *  - 不參與費用審核（review / disburse 皆 403）、無財務總覽
 *  - boss 可透過成員管理指派 manager 角色
 */
class ManagerRoleTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $boss;
    private User $manager;
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
            'name' => 'MgrCo', 'status' => 'active',
            'invite_code' => 'MGRCO1234567', 'created_by' => $admin->id,
        ]);

        $this->boss = User::factory()->create([
            'role' => 'boss', 'status' => 'active', 'company_id' => $this->company->id,
        ]);
        $this->manager = User::factory()->create([
            'role' => 'manager', 'status' => 'active', 'company_id' => $this->company->id,
        ]);
    }

    private function projectPayload(): array
    {
        return [
            'name'         => 'PM 的專案',
            'category_id'  => $this->category->id,
            'priority_id'  => $this->priority->id,
            'status_id'    => $this->status->id,
            'start_date'   => now()->toDateString(),
            'total_budget' => 50000,
        ];
    }

    private function makeProject(User $owner): Project
    {
        return Project::create([
            'name'        => $owner->name . ' 的專案',
            'category_id' => $this->category->id,
            'priority_id' => $this->priority->id,
            'status_id'   => $this->status->id,
            'start_date'  => now(),
            'owner_id'    => $owner->id,
            'created_by'  => $owner->id,
            'company_id'  => $this->company->id,
            'total_budget' => 10000,
        ]);
    }

    public function test_manager_can_create_project_and_becomes_owner(): void
    {
        $res = $this->actingAs($this->manager)
            ->postJson('/api/projects', $this->projectPayload())
            ->assertCreated()
            ->json();

        $this->assertSame($this->manager->id, $res['owner_id']);
        $this->assertSame($this->company->id, $res['company_id']);
    }

    public function test_manager_can_update_own_project_but_not_others(): void
    {
        $own   = $this->makeProject($this->manager);
        $other = $this->makeProject($this->boss);

        $this->actingAs($this->manager)
            ->putJson("/api/projects/{$own->id}", ['name' => '改名'])
            ->assertOk();

        $this->actingAs($this->manager)
            ->putJson("/api/projects/{$other->id}", ['name' => '入侵'])
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->deleteJson("/api/projects/{$other->id}")
            ->assertForbidden();
    }

    public function test_member_cannot_create_project(): void
    {
        $member = User::factory()->create([
            'role' => 'member', 'status' => 'active', 'company_id' => $this->company->id,
        ]);

        $this->actingAs($member)
            ->postJson('/api/projects', $this->projectPayload())
            ->assertForbidden();
    }

    public function test_manager_can_submit_fee_on_own_project_task(): void
    {
        $project = $this->makeProject($this->manager);
        $task = Task::create([
            'project_id' => $project->id, 'name' => 'PM 任務',
            'assignee_id' => $this->manager->id,
            'start_date' => now(), 'end_date' => now()->addDay(),
            'status_id' => $this->status->id, 'priority_id' => $this->priority->id,
            'created_by' => $this->manager->id,
        ]);

        $this->actingAs($this->manager)
            ->postJson("/api/projects/{$project->id}/tasks/{$task->id}/fees", [
                'amount' => 800, 'note' => '出差費',
            ])
            ->assertCreated();
    }

    public function test_manager_cannot_review_or_disburse_fees(): void
    {
        $project = $this->makeProject($this->manager);
        $member = User::factory()->create([
            'role' => 'member', 'status' => 'active', 'company_id' => $this->company->id,
        ]);
        $task = Task::create([
            'project_id' => $project->id, 'name' => '任務',
            'assignee_id' => $member->id,
            'start_date' => now(), 'end_date' => now()->addDay(),
            'status_id' => $this->status->id, 'priority_id' => $this->priority->id,
            'created_by' => $this->manager->id,
        ]);
        $fee = TaskFee::create([
            'task_id' => $task->id, 'project_id' => $project->id,
            'submitted_by' => $member->id, 'amount' => 100, 'status' => 'pending',
        ]);

        $this->actingAs($this->manager)
            ->postJson("/api/task-fees/{$fee->id}/review")
            ->assertForbidden();

        $fee->update(['status' => 'reviewed']);

        $this->actingAs($this->manager)
            ->postJson("/api/task-fees/{$fee->id}/disburse")
            ->assertForbidden();

        // 費用審核頁 / 財務總覽也不可進
        $this->actingAs($this->manager)->getJson('/api/manager/fee-reviews')->assertForbidden();
        $this->actingAs($this->manager)->getJson('/api/finance/overview')->assertForbidden();
    }

    public function test_boss_can_assign_manager_role(): void
    {
        $target = User::factory()->create([
            'role' => 'member', 'status' => 'active', 'company_id' => $this->company->id,
        ]);

        $this->actingAs($this->boss)
            ->putJson("/api/manager/members/{$target->id}", ['role' => 'manager'])
            ->assertOk()
            ->assertJsonPath('role', 'manager');

        // 升職後仍出現在成員管理列表、且可再被編輯（例如降回 member）
        $list = $this->actingAs($this->boss)->getJson('/api/manager/members')->assertOk()->json();
        $this->assertNotNull(collect($list)->firstWhere('id', $target->id));

        $this->actingAs($this->boss)
            ->putJson("/api/manager/members/{$target->id}", ['role' => 'member'])
            ->assertOk()
            ->assertJsonPath('role', 'member');
    }
}
