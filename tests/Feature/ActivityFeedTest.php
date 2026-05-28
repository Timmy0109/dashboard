<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Priority;
use App\Models\Project;
use App\Models\StatusRule;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityFeedTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $member;
    private Project $project;
    private Task $task;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $status   = StatusRule::create(['name' => '進行中', 'icon' => '▶', 'color' => '#3b82f6', 'sort_order' => 1, 'is_active' => true]);
        $priority = Priority::create(['name' => '中', 'color' => '#f59e0b', 'sort_order' => 2, 'is_active' => true]);
        $category = Category::create(['name' => '開發', 'color' => '#6366f1', 'is_active' => true]);

        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->company = Company::create([
            'name'        => 'Co',
            'status'      => 'active',
            'invite_code' => 'CODE12345678',
            'created_by'  => $this->admin->id,
        ]);
        $this->admin->update(['company_id' => $this->company->id]);
        $this->member = User::factory()->create(['role' => 'member', 'status' => 'active', 'company_id' => $this->company->id]);

        $this->project = Project::create([
            'project_no'  => 'P001',
            'name'        => 'P',
            'company_id'  => $this->company->id,
            'start_date'  => '2026-01-01',
            'due_date'    => '2026-03-31',
            'status_id'   => $status->id,
            'priority_id' => $priority->id,
            'category_id' => $category->id,
            'owner_id'    => $this->admin->id,
            'created_by'  => $this->admin->id,
        ]);
        $this->project->members()->attach($this->member->id, ['role' => 'member']);

        $this->task = Task::create([
            'project_id'   => $this->project->id,
            'name'         => 'T',
            'start_date'   => '2026-01-01',
            'end_date'     => '2026-01-31',
            'status_id'    => $status->id,
            'priority_id'  => $priority->id,
            'progress'     => 0,
            'is_completed' => false,
            'created_by'   => $this->admin->id,
        ]);

        // Seed 3 activities
        for ($i = 0; $i < 3; $i++) {
            TaskActivity::create([
                'task_id'    => $this->task->id,
                'actor_id'   => $this->admin->id,
                'event'      => 'created',
                'payload'    => null,
                'created_at' => now(),
            ]);
        }
    }

    public function test_admin_company_scope_returns_activities(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/api/activity?scope=company')
            ->assertOk()
            ->assertJsonCount(4, 'data'); // 3 seeded + 1 auto-created by TaskObserver
    }

    public function test_member_company_scope_filtered_by_membership(): void
    {
        $this->actingAs($this->member)
            ->getJson('/api/activity?scope=company')
            ->assertOk()
            ->assertJsonCount(4, 'data'); // 3 seeded + 1 auto-created by TaskObserver
    }

    public function test_project_scope_allowed_for_member(): void
    {
        $this->actingAs($this->member)
            ->getJson("/api/activity?scope=project:{$this->project->id}")
            ->assertOk()
            ->assertJsonCount(4, 'data'); // 3 seeded + 1 auto-created by TaskObserver
    }

    public function test_non_member_cannot_access_project_scope(): void
    {
        $outsider = User::factory()->create(['role' => 'member', 'status' => 'active', 'company_id' => $this->company->id]);

        $this->actingAs($outsider)
            ->getJson("/api/activity?scope=project:{$this->project->id}")
            ->assertStatus(403);
    }

    public function test_empty_when_user_has_no_projects(): void
    {
        $other = User::factory()->create(['role' => 'member', 'status' => 'active', 'company_id' => $this->company->id]);

        $this->actingAs($other)
            ->getJson('/api/activity?scope=company')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
