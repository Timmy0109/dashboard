<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Priority;
use App\Models\Project;
use App\Models\StatusRule;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    private StatusRule $status;
    private Priority $priority;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->status   = StatusRule::create(['name' => '進行中', 'icon' => '▶', 'color' => '#3b82f6', 'sort_order' => 1, 'is_active' => true]);
        $this->priority = Priority::create(['name' => '中', 'color' => '#f59e0b', 'sort_order' => 2, 'is_active' => true]);
        $this->category = Category::create(['name' => '開發', 'color' => '#6366f1', 'is_active' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function makeProject(User $owner): Project
    {
        return Project::create([
            'project_no'  => 'P' . rand(100, 999),
            'name'        => 'Stats Project',
            'start_date'  => '2026-01-01',
            'due_date'    => '2026-03-31',
            'status_id'   => $this->status->id,
            'priority_id' => $this->priority->id,
            'category_id' => $this->category->id,
            'owner_id'    => $owner->id,
            'created_by'  => $owner->id,
        ]);
    }

    public function test_stats_index_returns_expected_structure(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->getJson('/api/stats')
            ->assertOk()
            ->assertJsonStructure([
                'status_distribution',
                'project_progress',
                'task_workload',
                'completion_trend',
                'totals' => ['projects', 'tasks', 'completed_tasks', 'overdue_tasks', 'members'],
            ]);
    }

    public function test_stats_totals_count_projects_and_tasks(): void
    {
        $admin   = $this->admin();
        $project = $this->makeProject($admin);

        Task::create([
            'project_id'  => $project->id,
            'name'        => 'T1',
            'start_date'  => '2026-01-01',
            'end_date'    => '2026-01-31',
            'status_id'   => $this->status->id,
            'priority_id' => $this->priority->id,
            'progress'    => 0,
            'is_completed'=> false,
            'created_by'  => $admin->id,
        ]);

        $res = $this->actingAs($admin)->getJson('/api/stats')->assertOk();
        $this->assertGreaterThanOrEqual(1, $res->json('totals.projects'));
        $this->assertGreaterThanOrEqual(1, $res->json('totals.tasks'));
    }

    public function test_member_detail_returns_summary(): void
    {
        $admin   = $this->admin();
        $member  = User::factory()->create(['role' => 'member', 'status' => 'active']);
        $project = $this->makeProject($admin);

        Task::create([
            'project_id'  => $project->id,
            'name'        => 'Assigned Task',
            'start_date'  => '2026-01-01',
            'end_date'    => '2026-01-31',
            'status_id'   => $this->status->id,
            'priority_id' => $this->priority->id,
            'assignee_id' => $member->id,
            'progress'    => 30,
            'is_completed'=> false,
            'created_by'  => $admin->id,
        ]);

        $this->actingAs($admin)
            ->getJson("/api/stats/member/{$member->id}")
            ->assertOk()
            ->assertJsonStructure([
                'member' => ['id', 'name', 'email'],
                'summary' => ['total', 'completed', 'pending', 'in_progress', 'overdue', 'due_soon', 'avg_progress', 'project_count'],
                'by_project',
                'tasks',
            ])
            ->assertJsonPath('summary.total', 1)
            ->assertJsonPath('summary.in_progress', 1);
    }

    public function test_stats_requires_auth(): void
    {
        $this->getJson('/api/stats')->assertUnauthorized();
    }

    public function test_member_without_feature_flag_cannot_access_stats(): void
    {
        $creator = $this->admin();
        $company = Company::create(['name' => 'NoCo', 'invite_code' => 'NOCONCE1', 'status' => 'active', 'created_by' => $creator->id]);
        $member  = User::factory()->create(['role' => 'member', 'status' => 'active', 'company_id' => $company->id]);

        $this->actingAs($member)->getJson('/api/stats')->assertForbidden();
    }
}
