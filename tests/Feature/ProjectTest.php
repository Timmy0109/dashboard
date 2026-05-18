<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Priority;
use App\Models\Project;
use App\Models\StatusRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
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

    private function makeCompany(User $creator, string $code): Company
    {
        return Company::create(['name' => 'TestCo', 'invite_code' => $code, 'status' => 'active', 'created_by' => $creator->id]);
    }

    private function projectPayload(User $owner): array
    {
        return [
            'name'        => 'Test Project',
            'start_date'  => '2026-01-01',
            'due_date'    => '2026-03-31',
            'status_id'   => $this->status->id,
            'priority_id' => $this->priority->id,
            'category_id' => $this->category->id,
            'owner_id'    => $owner->id,
        ];
    }

    public function test_admin_can_create_project(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/projects', $this->projectPayload($admin))
            ->assertCreated()
            ->assertJsonPath('name', 'Test Project');
    }

    public function test_manager_can_create_project(): void
    {
        $admin   = $this->admin();
        $company = $this->makeCompany($admin, 'ABCD1234');
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active', 'company_id' => $company->id]);

        $this->actingAs($manager)
            ->postJson('/api/projects', $this->projectPayload($manager))
            ->assertCreated();
    }

    public function test_member_cannot_create_project(): void
    {
        $admin   = $this->admin();
        $company = $this->makeCompany($admin, 'XYZW5678');
        $member  = User::factory()->create(['role' => 'member', 'status' => 'active', 'company_id' => $company->id]);

        $this->actingAs($member)
            ->postJson('/api/projects', $this->projectPayload($member))
            ->assertForbidden();
    }

    public function test_admin_can_list_all_projects(): void
    {
        $admin = $this->admin();
        Project::create(array_merge($this->projectPayload($admin), ['project_no' => 'P001', 'created_by' => $admin->id]));

        // index returns a flat JSON array, not paginated
        $this->actingAs($admin)->getJson('/api/projects')->assertOk()->assertJsonStructure([['id', 'name']]);
    }

    public function test_owner_can_update_project(): void
    {
        $admin   = $this->admin();
        $project = Project::create(array_merge($this->projectPayload($admin), ['project_no' => 'P002', 'created_by' => $admin->id]));

        $this->actingAs($admin)
            ->putJson("/api/projects/{$project->id}", ['name' => 'Updated Name'])
            ->assertOk()
            ->assertJsonPath('name', 'Updated Name');
    }

    public function test_non_owner_member_cannot_update_project(): void
    {
        $admin   = $this->admin();
        $company = $this->makeCompany($admin, 'CO2CO2CO');
        $member  = User::factory()->create(['role' => 'member', 'status' => 'active', 'company_id' => $company->id]);
        $project = Project::create(array_merge($this->projectPayload($admin), ['project_no' => 'P003', 'created_by' => $admin->id, 'company_id' => $company->id]));

        $this->actingAs($member)
            ->putJson("/api/projects/{$project->id}", ['name' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_owner_can_delete_project(): void
    {
        $admin   = $this->admin();
        $project = Project::create(array_merge($this->projectPayload($admin), ['project_no' => 'P004', 'created_by' => $admin->id]));

        $this->actingAs($admin)->deleteJson("/api/projects/{$project->id}")->assertNoContent();
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }
}
