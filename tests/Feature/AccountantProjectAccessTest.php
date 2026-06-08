<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Priority;
use App\Models\Project;
use App\Models\StatusRule;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 迴歸：會計（accountant）與同公司非 owner 老闆（boss）從首頁總覽
 * 點進專案頁時，三個子資源端點不應再 403：
 *   GET /api/projects/{id}/task-fees
 *   GET /api/projects/{id}/admin-fees
 *   GET /api/projects/{id}/attachments
 *
 * 但寫入（PUT/DELETE project、POST admin-fees）與跨公司存取仍須被擋；
 * admin 對費用端點維持原行為。
 */
class AccountantProjectAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $boss;          // owner of the project
    private User $accountant;    // same company, NOT a project member
    private User $otherBoss;     // same company boss, NOT owner / NOT member
    private User $member;        // same company member, NOT a project member
    private User $foreignAccountant; // accountant from a different company
    private Project $project;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $status   = StatusRule::create(['name' => '進行中', 'icon' => '▶', 'color' => '#3b82f6', 'sort_order' => 1, 'is_active' => true]);
        $priority = Priority::create(['name' => '中', 'color' => '#f59e0b', 'sort_order' => 2, 'is_active' => true]);
        $category = Category::create(['name' => '開發', 'color' => '#6366f1', 'is_active' => true]);

        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $company = Company::create([
            'name' => 'Co', 'status' => 'active',
            'invite_code' => 'CODE12345678', 'created_by' => $admin->id,
        ]);

        $this->boss              = User::factory()->create(['role' => 'boss', 'status' => 'active', 'company_id' => $company->id]);
        $this->accountant        = User::factory()->create(['role' => 'accountant', 'status' => 'active', 'company_id' => $company->id]);
        $this->otherBoss         = User::factory()->create(['role' => 'boss', 'status' => 'active', 'company_id' => $company->id]);
        $this->member            = User::factory()->create(['role' => 'member', 'status' => 'active', 'company_id' => $company->id]);

        $otherCompany = Company::create([
            'name' => 'Other', 'status' => 'active',
            'invite_code' => 'OTHER1234567', 'created_by' => $admin->id,
        ]);
        $this->foreignAccountant = User::factory()->create(['role' => 'accountant', 'status' => 'active', 'company_id' => $otherCompany->id]);

        $this->project = Project::create([
            'name'         => 'P1',
            'category_id'  => $category->id,
            'owner_id'     => $this->boss->id,
            'priority_id'  => $priority->id,
            'status_id'    => $status->id,
            'start_date'   => now(),
            'created_by'   => $this->boss->id,
            'company_id'   => $company->id,
        ]);

        // accountant / otherBoss / member 都【不】是專案成員
        $this->task = Task::create([
            'project_id'  => $this->project->id,
            'name'        => 'T1',
            'start_date'  => now(),
            'end_date'    => now()->addDays(7),
            'status_id'   => $status->id,
            'priority_id' => $priority->id,
            'created_by'  => $this->boss->id,
        ]);

        TaskAttachment::create([
            'task_id'       => $this->task->id,
            'uploader_id'   => $this->boss->id,
            'original_name' => 'a.pdf',
            'disk_path'     => 'attachments/a.pdf',
            'mime_type'     => 'application/pdf',
            'size'          => 1024,
        ]);

        $this->project->adminFees()->create([
            'created_by'  => $this->boss->id,
            'amount'      => 500,
            'note'        => 'x',
            'incurred_on' => now()->toDateString(),
        ]);
    }

    // ---- 會計（同公司、非成員）：讀取應通過 ----

    public function test_accountant_can_view_project(): void
    {
        $this->actingAs($this->accountant)
            ->getJson("/api/projects/{$this->project->id}")
            ->assertOk();
    }

    public function test_accountant_can_list_task_fees(): void
    {
        $this->actingAs($this->accountant)
            ->getJson("/api/projects/{$this->project->id}/task-fees")
            ->assertOk();
    }

    public function test_accountant_can_list_admin_fees(): void
    {
        $this->actingAs($this->accountant)
            ->getJson("/api/projects/{$this->project->id}/admin-fees")
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_accountant_can_list_attachments(): void
    {
        $this->actingAs($this->accountant)
            ->getJson("/api/projects/{$this->project->id}/attachments")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ---- 同公司非 owner 老闆：讀取應通過 ----

    public function test_same_company_non_owner_boss_can_read_subresources(): void
    {
        $this->actingAs($this->otherBoss)
            ->getJson("/api/projects/{$this->project->id}/task-fees")
            ->assertOk();
        $this->actingAs($this->otherBoss)
            ->getJson("/api/projects/{$this->project->id}/admin-fees")
            ->assertOk();
        $this->actingAs($this->otherBoss)
            ->getJson("/api/projects/{$this->project->id}/attachments")
            ->assertOk();
    }

    // ---- 跨公司會計：仍 403 ----

    public function test_foreign_accountant_forbidden(): void
    {
        $this->actingAs($this->foreignAccountant)
            ->getJson("/api/projects/{$this->project->id}")
            ->assertStatus(403);
        $this->actingAs($this->foreignAccountant)
            ->getJson("/api/projects/{$this->project->id}/task-fees")
            ->assertStatus(403);
        $this->actingAs($this->foreignAccountant)
            ->getJson("/api/projects/{$this->project->id}/admin-fees")
            ->assertStatus(403);
        $this->actingAs($this->foreignAccountant)
            ->getJson("/api/projects/{$this->project->id}/attachments")
            ->assertStatus(403);
    }

    // ---- 一般 member（同公司、非成員）：仍 403 ----

    public function test_non_member_member_still_forbidden(): void
    {
        $this->actingAs($this->member)
            ->getJson("/api/projects/{$this->project->id}/task-fees")
            ->assertStatus(403);
        $this->actingAs($this->member)
            ->getJson("/api/projects/{$this->project->id}/attachments")
            ->assertStatus(403);
    }

    // ---- 寫入仍須被擋 ----

    public function test_accountant_cannot_update_project(): void
    {
        $this->actingAs($this->accountant)
            ->putJson("/api/projects/{$this->project->id}", ['name' => 'hacked'])
            ->assertStatus(403);
    }

    public function test_accountant_cannot_delete_project(): void
    {
        $this->actingAs($this->accountant)
            ->deleteJson("/api/projects/{$this->project->id}")
            ->assertStatus(403);
    }

    public function test_accountant_cannot_create_admin_fee(): void
    {
        $this->actingAs($this->accountant)
            ->postJson("/api/projects/{$this->project->id}/admin-fees", ['amount' => 100])
            ->assertStatus(403);
    }

    public function test_same_company_non_owner_boss_cannot_create_admin_fee(): void
    {
        $this->actingAs($this->otherBoss)
            ->postJson("/api/projects/{$this->project->id}/admin-fees", ['amount' => 100])
            ->assertStatus(403);
    }

    public function test_accountant_cannot_post_comment(): void
    {
        $this->actingAs($this->accountant)
            ->postJson("/api/projects/{$this->project->id}/tasks/{$this->task->id}/comments", ['body' => 'hi'])
            ->assertStatus(403);
    }

    public function test_accountant_cannot_upload_attachment(): void
    {
        $this->actingAs($this->accountant)
            ->postJson("/api/projects/{$this->project->id}/tasks/{$this->task->id}/attachments", [])
            ->assertStatus(403);
    }
}
