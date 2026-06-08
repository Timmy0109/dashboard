<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Priority;
use App\Models\Project;
use App\Models\ProjectAdminFee;
use App\Models\StatusRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 行政費審核流程（PM 建立 → 會計審核）：
 *  - boss 建立 → 直接 approved（免審）
 *  - manager 在自己專案建立 → pending（待審）
 *  - 會計核准/退件；無會計的公司由老闆兼審
 *  - manager 不可審核；member 不可建立
 *  - 僅 approved 計入專案支出/預算
 */
class ProjectAdminFeeReviewTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $boss;
    private User $manager;
    private User $accountant;
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
            'name' => 'AdminFeeCo', 'status' => 'active',
            'invite_code' => 'ADMINFEE0001', 'created_by' => $admin->id,
        ]);

        $this->boss = User::factory()->create([
            'role' => 'boss', 'status' => 'active', 'company_id' => $this->company->id,
        ]);
        $this->manager = User::factory()->create([
            'role' => 'manager', 'status' => 'active', 'company_id' => $this->company->id,
        ]);
        $this->accountant = User::factory()->create([
            'role' => 'accountant', 'status' => 'active', 'company_id' => $this->company->id,
        ]);
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
            'total_budget' => 100000,
        ]);
    }

    public function test_manager_created_admin_fee_is_pending(): void
    {
        $project = $this->makeProject($this->manager);

        $this->actingAs($this->manager)
            ->postJson("/api/projects/{$project->id}/admin-fees", ['amount' => 1200, 'note' => '文具'])
            ->assertCreated()
            ->assertJsonPath('status', ProjectAdminFee::STATUS_PENDING);
    }

    public function test_boss_created_admin_fee_is_auto_approved(): void
    {
        $project = $this->makeProject($this->boss);

        $this->actingAs($this->boss)
            ->postJson("/api/projects/{$project->id}/admin-fees", ['amount' => 3000, 'note' => '雜支'])
            ->assertCreated()
            ->assertJsonPath('status', ProjectAdminFee::STATUS_APPROVED);
    }

    public function test_member_cannot_create_admin_fee(): void
    {
        $member = User::factory()->create([
            'role' => 'member', 'status' => 'active', 'company_id' => $this->company->id,
        ]);
        $project = $this->makeProject($this->manager);

        $this->actingAs($member)
            ->postJson("/api/projects/{$project->id}/admin-fees", ['amount' => 500])
            ->assertForbidden();
    }

    public function test_manager_cannot_create_admin_fee_on_others_project(): void
    {
        $project = $this->makeProject($this->boss); // 不是 manager 的專案

        $this->actingAs($this->manager)
            ->postJson("/api/projects/{$project->id}/admin-fees", ['amount' => 500])
            ->assertForbidden();
    }

    public function test_accountant_can_approve_pending_admin_fee(): void
    {
        $fee = $this->pendingFeeOnManagerProject();

        $this->actingAs($this->accountant)
            ->postJson("/api/project-admin-fees/{$fee->id}/review", ['decision' => 'approve'])
            ->assertOk()
            ->assertJsonPath('status', ProjectAdminFee::STATUS_APPROVED)
            ->assertJsonPath('reviewed_by', $this->accountant->id);
    }

    public function test_accountant_can_reject_pending_admin_fee(): void
    {
        $fee = $this->pendingFeeOnManagerProject();

        $this->actingAs($this->accountant)
            ->postJson("/api/project-admin-fees/{$fee->id}/review", [
                'decision' => 'reject', 'review_note' => '缺收據',
            ])
            ->assertOk()
            ->assertJsonPath('status', ProjectAdminFee::STATUS_REJECTED)
            ->assertJsonPath('review_note', '缺收據');
    }

    public function test_manager_cannot_review_admin_fee(): void
    {
        $fee = $this->pendingFeeOnManagerProject();

        $this->actingAs($this->manager)
            ->postJson("/api/project-admin-fees/{$fee->id}/review", ['decision' => 'approve'])
            ->assertForbidden();
    }

    public function test_boss_reviews_admin_fee_when_no_accountant(): void
    {
        // 另開一間沒有會計的公司，由老闆兼審
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $co = Company::create([
            'name' => 'NoAcctCo', 'status' => 'active',
            'invite_code' => 'NOACCT000001', 'created_by' => $admin->id,
        ]);
        $boss = User::factory()->create(['role' => 'boss', 'status' => 'active', 'company_id' => $co->id]);
        $mgr  = User::factory()->create(['role' => 'manager', 'status' => 'active', 'company_id' => $co->id]);

        $project = Project::create([
            'name' => 'NoAcct 專案', 'category_id' => $this->category->id,
            'priority_id' => $this->priority->id, 'status_id' => $this->status->id,
            'start_date' => now(), 'owner_id' => $mgr->id, 'created_by' => $mgr->id,
            'company_id' => $co->id, 'total_budget' => 50000,
        ]);
        $fee = ProjectAdminFee::create([
            'project_id' => $project->id, 'created_by' => $mgr->id,
            'amount' => 800, 'status' => ProjectAdminFee::STATUS_PENDING,
        ]);

        $this->actingAs($boss)
            ->postJson("/api/project-admin-fees/{$fee->id}/review", ['decision' => 'approve'])
            ->assertOk()
            ->assertJsonPath('status', ProjectAdminFee::STATUS_APPROVED);
    }

    public function test_boss_cannot_review_admin_fee_when_company_has_accountant(): void
    {
        // 本公司有會計 → 老闆不兼審（會計專有）
        $fee = $this->pendingFeeOnManagerProject();

        $this->actingAs($this->boss)
            ->postJson("/api/project-admin-fees/{$fee->id}/review", ['decision' => 'approve'])
            ->assertForbidden();
    }

    public function test_only_approved_admin_fee_counts_in_summary(): void
    {
        $project = $this->makeProject($this->manager);
        // 一筆 pending、一筆 approved
        ProjectAdminFee::create([
            'project_id' => $project->id, 'created_by' => $this->manager->id,
            'amount' => 1000, 'status' => ProjectAdminFee::STATUS_PENDING,
        ]);
        ProjectAdminFee::create([
            'project_id' => $project->id, 'created_by' => $this->boss->id,
            'amount' => 2500, 'status' => ProjectAdminFee::STATUS_APPROVED,
        ]);

        // 專案經理檢視自己專案的財務全貌（scope=all）
        $res = $this->actingAs($this->manager)
            ->getJson("/api/projects/{$project->id}/fee-summary")
            ->assertOk()
            ->json();

        $this->assertSame('all', $res['scope']);
        $this->assertEquals(2500, $res['admin_fees']);
        $this->assertEquals(1000, $res['admin_fees_pending']);
        $this->assertEquals(2500, $res['total']); // 僅 approved 計入支出
    }

    public function test_manager_can_edit_own_pending_but_not_after_approved(): void
    {
        $project = $this->makeProject($this->manager);
        $fee = ProjectAdminFee::create([
            'project_id' => $project->id, 'created_by' => $this->manager->id,
            'amount' => 900, 'status' => ProjectAdminFee::STATUS_PENDING,
        ]);

        // pending：可改
        $this->actingAs($this->manager)
            ->patchJson("/api/project-admin-fees/{$fee->id}", ['amount' => 950])
            ->assertOk();

        // 核准後：不可再改
        $fee->update(['status' => ProjectAdminFee::STATUS_APPROVED]);
        $this->actingAs($this->manager)
            ->patchJson("/api/project-admin-fees/{$fee->id}", ['amount' => 1000])
            ->assertForbidden();
    }

    private function pendingFeeOnManagerProject(): ProjectAdminFee
    {
        $project = $this->makeProject($this->manager);

        return ProjectAdminFee::create([
            'project_id' => $project->id, 'created_by' => $this->manager->id,
            'amount' => 1500, 'status' => ProjectAdminFee::STATUS_PENDING,
        ]);
    }
}
