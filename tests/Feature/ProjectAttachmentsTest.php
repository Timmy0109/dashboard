<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Priority;
use App\Models\Project;
use App\Models\StatusRule;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $outsider;
    private Project $project;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $status   = StatusRule::create(['name' => '進行中', 'icon' => '▶', 'color' => '#3b82f6', 'sort_order' => 1, 'is_active' => true]);
        $priority = Priority::create(['name' => '中', 'color' => '#f59e0b', 'sort_order' => 2, 'is_active' => true]);
        $category = Category::create(['name' => '開發', 'color' => '#6366f1', 'is_active' => true]);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->outsider = User::factory()->create(['role' => 'member', 'status' => 'active']);

        $this->project = Project::create([
            'project_no'  => 'P001',
            'name'        => 'P',
            'start_date'  => '2026-01-01',
            'due_date'    => '2026-03-31',
            'status_id'   => $status->id,
            'priority_id' => $priority->id,
            'category_id' => $category->id,
            'owner_id'    => $this->admin->id,
            'created_by'  => $this->admin->id,
        ]);

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
    }

    public function test_admin_can_list_project_attachments(): void
    {
        TaskAttachment::create([
            'task_id'       => $this->task->id,
            'uploader_id'   => $this->admin->id,
            'original_name' => 'a.pdf',
            'disk_path'     => 'attachments/a.pdf',
            'mime_type'     => 'application/pdf',
            'size'          => 1024,
        ]);
        TaskAttachment::create([
            'task_id'       => $this->task->id,
            'uploader_id'   => $this->admin->id,
            'original_name' => 'b.png',
            'disk_path'     => 'attachments/b.png',
            'mime_type'     => 'image/png',
            'size'          => 2048,
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/projects/{$this->project->id}/attachments")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'original_name', 'task_id', 'task_name', 'uploader']], 'next_cursor']);
    }

    public function test_empty_project_returns_empty_list(): void
    {
        $this->actingAs($this->admin)
            ->getJson("/api/projects/{$this->project->id}/attachments")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_outsider_forbidden(): void
    {
        $this->actingAs($this->outsider)
            ->getJson("/api/projects/{$this->project->id}/attachments")
            ->assertStatus(403);
    }
}
