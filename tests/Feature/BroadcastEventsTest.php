<?php

namespace Tests\Feature;

use App\Events\ActivityRecorded;
use App\Events\NotificationCreated;
use App\Events\TaskAttachmentUploaded;
use App\Events\TaskCommentCreated;
use App\Events\TaskCommentReplied;
use App\Events\TaskHistoryEventRecorded;
use App\Models\Category;
use App\Models\Company;
use App\Models\Priority;
use App\Models\Project;
use App\Models\StatusRule;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BroadcastEventsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $bob;
    private Project $project;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $status   = StatusRule::create(['name' => '進行中', 'icon' => '▶', 'color' => '#3b82f6', 'sort_order' => 1, 'is_active' => true]);
        $priority = Priority::create(['name' => '中', 'color' => '#f59e0b', 'sort_order' => 2, 'is_active' => true]);
        $category = Category::create(['name' => '開發', 'color' => '#6366f1', 'is_active' => true]);

        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $company = Company::create([
            'name'        => 'Co',
            'status'      => 'active',
            'invite_code' => 'CODE12345678',
            'created_by'  => $this->admin->id,
        ]);
        $this->admin->update(['company_id' => $company->id]);
        $this->bob = User::factory()->create(['name' => 'Bob', 'role' => 'member', 'status' => 'active', 'company_id' => $company->id]);

        $this->project = Project::create([
            'project_no'  => 'P001',
            'name'        => 'P',
            'company_id'  => $company->id,
            'start_date'  => '2026-01-01',
            'due_date'    => '2026-03-31',
            'status_id'   => $status->id,
            'priority_id' => $priority->id,
            'category_id' => $category->id,
            'owner_id'    => $this->admin->id,
            'created_by'  => $this->admin->id,
        ]);
        $this->project->members()->attach($this->bob->id, ['role' => 'member']);

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

    public function test_task_comment_created_fires_event(): void
    {
        Event::fake([TaskCommentCreated::class]);

        $this->actingAs($this->admin)
            ->postJson("/api/projects/{$this->project->id}/tasks/{$this->task->id}/comments", ['body' => 'hi'])
            ->assertCreated();

        Event::assertDispatched(TaskCommentCreated::class);
    }

    public function test_task_comment_replied_fires_event(): void
    {
        $parent = TaskComment::create([
            'task_id' => $this->task->id,
            'user_id' => $this->admin->id,
            'body'    => 'p',
        ]);

        Event::fake([TaskCommentReplied::class]);

        $this->actingAs($this->admin)
            ->postJson("/api/projects/{$this->project->id}/tasks/{$this->task->id}/comments/{$parent->id}/reply", ['body' => 'r'])
            ->assertCreated();

        Event::assertDispatched(TaskCommentReplied::class);
    }

    public function test_attachment_uploaded_fires_event(): void
    {
        Storage::fake('local');
        Event::fake([TaskAttachmentUploaded::class]);

        $this->actingAs($this->admin)
            ->post("/api/projects/{$this->project->id}/tasks/{$this->task->id}/attachments", [
                'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            ])
            ->assertCreated();

        Event::assertDispatched(TaskAttachmentUploaded::class);
    }

    public function test_assignee_change_creates_notification_and_fires_event(): void
    {
        Event::fake([NotificationCreated::class]);

        $this->actingAs($this->admin)
            ->putJson("/api/projects/{$this->project->id}/tasks/{$this->task->id}", [
                'assignee_id' => $this->bob->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->bob->id,
            'type'    => 'task_assigned',
        ]);

        Event::assertDispatched(NotificationCreated::class);
    }

    public function test_mention_in_comment_fires_notification(): void
    {
        Event::fake([NotificationCreated::class]);

        $this->actingAs($this->admin)
            ->postJson("/api/projects/{$this->project->id}/tasks/{$this->task->id}/comments", [
                'body' => 'hey @Bob check this',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->bob->id,
            'type'    => 'task_mentioned',
        ]);

        Event::assertDispatched(NotificationCreated::class);
    }

    public function test_task_activity_created_fires_history_and_activity_recorded(): void
    {
        Event::fake([TaskHistoryEventRecorded::class, ActivityRecorded::class]);

        // Trigger by creating a task; observer auto-creates a `created` TaskActivity.
        $this->actingAs($this->admin)
            ->postJson("/api/projects/{$this->project->id}/tasks", [
                'name'        => 'New T',
                'start_date'  => '2026-02-01',
                'end_date'    => '2026-02-15',
                'status_id'   => $this->task->status_id,
                'priority_id' => $this->task->priority_id,
            ])
            ->assertCreated();

        Event::assertDispatched(TaskHistoryEventRecorded::class);
        Event::assertDispatched(ActivityRecorded::class);
    }
}
