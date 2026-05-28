<?php

namespace Tests\Feature;

use App\Events\TaskCommentReplied;
use App\Models\Category;
use App\Models\Priority;
use App\Models\Project;
use App\Models\StatusRule;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CommentReplyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Project $project;
    private Task $task;
    private TaskComment $parentComment;

    protected function setUp(): void
    {
        parent::setUp();
        $status   = StatusRule::create(['name' => '進行中', 'icon' => '▶', 'color' => '#3b82f6', 'sort_order' => 1, 'is_active' => true]);
        $priority = Priority::create(['name' => '中', 'color' => '#f59e0b', 'sort_order' => 2, 'is_active' => true]);
        $category = Category::create(['name' => '開發', 'color' => '#6366f1', 'is_active' => true]);
        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        $this->project = Project::create([
            'project_no'  => 'P001',
            'name'        => 'Test Project',
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
            'name'         => 'T1',
            'start_date'   => '2026-01-01',
            'end_date'     => '2026-01-31',
            'status_id'    => $status->id,
            'priority_id'  => $priority->id,
            'progress'     => 0,
            'is_completed' => false,
            'created_by'   => $this->admin->id,
        ]);

        $this->parentComment = TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->admin->id,
            'body'    => 'Parent comment',
        ]);
    }

    public function test_admin_can_reply_to_comment(): void
    {
        Event::fake([TaskCommentReplied::class]);

        $this->actingAs($this->admin)
            ->postJson("/api/projects/{$this->project->id}/tasks/{$this->task->id}/comments/{$this->parentComment->id}/reply", [
                'body' => 'this is a reply',
            ])
            ->assertCreated()
            ->assertJsonFragment([
                'body'      => 'this is a reply',
                'parent_id' => $this->parentComment->id,
            ]);

        $this->assertDatabaseHas('task_comments', [
            'task_id'   => $this->task->id,
            'parent_id' => $this->parentComment->id,
            'body'      => 'this is a reply',
        ]);

        Event::assertDispatched(TaskCommentReplied::class);
    }

    public function test_reply_to_reply_is_rejected(): void
    {
        $firstReply = TaskComment::create([
            'task_id'   => $this->task->id,
            'user_id'   => $this->admin->id,
            'parent_id' => $this->parentComment->id,
            'body'      => 'first reply',
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/projects/{$this->project->id}/tasks/{$this->task->id}/comments/{$firstReply->id}/reply", [
                'body' => 'nested reply attempt',
            ])
            ->assertStatus(422);
    }

    public function test_reply_to_comment_on_different_task_returns_404(): void
    {
        $otherTask = Task::create([
            'project_id'   => $this->project->id,
            'name'         => 'Other',
            'start_date'   => '2026-01-01',
            'end_date'     => '2026-01-31',
            'status_id'    => $this->task->status_id,
            'priority_id'  => $this->task->priority_id,
            'progress'     => 0,
            'is_completed' => false,
            'created_by'   => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson("/api/projects/{$this->project->id}/tasks/{$otherTask->id}/comments/{$this->parentComment->id}/reply", [
                'body' => 'wrong task',
            ])
            ->assertNotFound();
    }

    public function test_reply_body_required(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/projects/{$this->project->id}/tasks/{$this->task->id}/comments/{$this->parentComment->id}/reply", [
                'body' => '',
            ])
            ->assertStatus(422);
    }
}
