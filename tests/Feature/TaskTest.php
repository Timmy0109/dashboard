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

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Project $project;
    private StatusRule $status;
    private Priority $priority;

    protected function setUp(): void
    {
        parent::setUp();
        $this->status   = StatusRule::create(['name' => '進行中', 'icon' => '▶', 'color' => '#3b82f6', 'sort_order' => 1, 'is_active' => true]);
        $this->priority = Priority::create(['name' => '中', 'color' => '#f59e0b', 'sort_order' => 2, 'is_active' => true]);
        $category       = Category::create(['name' => '開發', 'color' => '#6366f1', 'is_active' => true]);
        $this->admin    = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $this->project  = Project::create([
            'project_no'  => 'P001',
            'name'        => 'Test Project',
            'start_date'  => '2026-01-01',
            'due_date'    => '2026-03-31',
            'status_id'   => $this->status->id,
            'priority_id' => $this->priority->id,
            'category_id' => $category->id,
            'owner_id'    => $this->admin->id,
            'created_by'  => $this->admin->id,
        ]);
    }

    private function taskPayload(array $overrides = []): array
    {
        return array_merge([
            'name'        => 'Test Task',
            'start_date'  => '2026-01-01',
            'end_date'    => '2026-01-31',
            'status_id'   => $this->status->id,
            'priority_id' => $this->priority->id,
            'progress'    => 0,
            'is_completed'=> false,
        ], $overrides);
    }

    private function createTask(array $overrides = []): Task
    {
        return Task::create(array_merge([
            'project_id'  => $this->project->id,
            'name'        => 'Task',
            'start_date'  => '2026-01-01',
            'end_date'    => '2026-01-31',
            'status_id'   => $this->status->id,
            'priority_id' => $this->priority->id,
            'progress'    => 0,
            'is_completed'=> false,
            'created_by'  => $this->admin->id,
        ], $overrides));
    }

    public function test_admin_can_create_task(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/projects/{$this->project->id}/tasks", $this->taskPayload())
            ->assertCreated()
            ->assertJsonPath('name', 'Test Task');
    }

    public function test_task_requires_name(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/projects/{$this->project->id}/tasks", $this->taskPayload(['name' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_completing_task_forces_progress_100(): void
    {
        $task = $this->createTask(['progress' => 50]);

        $this->actingAs($this->admin)
            ->putJson("/api/projects/{$this->project->id}/tasks/{$task->id}", ['is_completed' => true])
            ->assertOk()
            ->assertJsonPath('progress', 100)
            ->assertJsonPath('is_completed', true);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'progress' => 100, 'is_completed' => 1]);
        $this->assertNotNull(Task::find($task->id)->completed_at);
    }

    public function test_reopening_task_resets_progress_and_completed_at(): void
    {
        $task = $this->createTask(['progress' => 100, 'is_completed' => true, 'completed_at' => now()]);

        $this->actingAs($this->admin)
            ->putJson("/api/projects/{$this->project->id}/tasks/{$task->id}", ['is_completed' => false])
            ->assertOk()
            ->assertJsonPath('progress', 0)
            ->assertJsonPath('is_completed', false);

        $this->assertNull(Task::find($task->id)->completed_at);
    }

    public function test_task_activities_are_recorded_on_create(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/projects/{$this->project->id}/tasks", $this->taskPayload())
            ->assertCreated();

        $task = Task::latest()->first();
        $this->assertDatabaseHas('task_activities', ['task_id' => $task->id, 'event' => 'created']);
    }

    public function test_activities_endpoint_returns_list(): void
    {
        $task = $this->createTask();

        $this->actingAs($this->admin)
            ->getJson("/api/projects/{$this->project->id}/tasks/{$task->id}/activities")
            ->assertOk()
            ->assertJsonStructure([['id', 'event', 'actor', 'created_at']]);
    }

    public function test_comments_can_be_created_and_listed(): void
    {
        $task = $this->createTask();

        $this->actingAs($this->admin)
            ->postJson("/api/projects/{$this->project->id}/tasks/{$task->id}/comments", ['body' => 'Hello!'])
            ->assertCreated();

        $this->actingAs($this->admin)
            ->getJson("/api/projects/{$this->project->id}/tasks/{$task->id}/comments")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.body', 'Hello!');
    }

    public function test_user_can_delete_own_comment(): void
    {
        $task = $this->createTask();

        $res = $this->actingAs($this->admin)
            ->postJson("/api/projects/{$this->project->id}/tasks/{$task->id}/comments", ['body' => 'Delete me'])
            ->assertCreated();

        $commentId = $res->json('id');

        $this->actingAs($this->admin)
            ->deleteJson("/api/projects/{$this->project->id}/tasks/{$task->id}/comments/{$commentId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('task_comments', ['id' => $commentId]);
    }

    public function test_other_user_cannot_delete_comment(): void
    {
        $other = User::factory()->create(['role' => 'member', 'status' => 'active']);
        $task  = $this->createTask();

        $res = $this->actingAs($this->admin)
            ->postJson("/api/projects/{$this->project->id}/tasks/{$task->id}/comments", ['body' => 'Mine'])
            ->assertCreated();

        $commentId = $res->json('id');

        $this->actingAs($other)
            ->deleteJson("/api/projects/{$this->project->id}/tasks/{$task->id}/comments/{$commentId}")
            ->assertForbidden();
    }

    public function test_delete_task(): void
    {
        $task = $this->createTask();

        $this->actingAs($this->admin)
            ->deleteJson("/api/projects/{$this->project->id}/tasks/{$task->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }
}
