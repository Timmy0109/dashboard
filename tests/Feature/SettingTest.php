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

class SettingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'status' => 'active']);
    }

    private function member(): User
    {
        return User::factory()->create(['role' => 'member', 'status' => 'active']);
    }

    // ── Categories ────────────────────────────────────────────────────────

    public function test_admin_can_create_category(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/settings/categories', ['name' => 'Dev', 'color' => '#3b82f6', 'is_active' => true])
            ->assertCreated()
            ->assertJsonPath('name', 'Dev');
    }

    public function test_non_admin_cannot_create_category(): void
    {
        $this->actingAs($this->member())
            ->postJson('/api/settings/categories', ['name' => 'Dev', 'color' => '#3b82f6', 'is_active' => true])
            ->assertForbidden();
    }

    public function test_cannot_delete_category_used_by_project(): void
    {
        $admin    = $this->admin();
        $status   = StatusRule::create(['name' => '進行中', 'icon' => '▶', 'color' => '#3b82f6', 'sort_order' => 1, 'is_active' => true]);
        $priority = Priority::create(['name' => '中', 'color' => '#f59e0b', 'sort_order' => 2, 'is_active' => true]);
        $category = Category::create(['name' => 'Used', 'color' => '#6366f1', 'is_active' => true]);

        Project::create([
            'project_no'  => 'P001',
            'name'        => 'Project',
            'start_date'  => '2026-01-01',
            'due_date'    => '2026-03-31',
            'status_id'   => $status->id,
            'priority_id' => $priority->id,
            'category_id' => $category->id,
            'owner_id'    => $admin->id,
            'created_by'  => $admin->id,
        ]);

        $this->actingAs($admin)
            ->deleteJson("/api/settings/categories/{$category->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', '此分類已被專案使用，無法刪除');
    }

    public function test_can_delete_unused_category(): void
    {
        $admin    = $this->admin();
        $category = Category::create(['name' => 'Unused', 'color' => '#aaa', 'is_active' => true]);

        $this->actingAs($admin)
            ->deleteJson("/api/settings/categories/{$category->id}")
            ->assertNoContent();
    }

    // ── Statuses ──────────────────────────────────────────────────────────

    public function test_admin_can_create_status(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/settings/statuses', ['name' => 'New', 'icon' => '★', 'color' => '#22c55e', 'sort_order' => 1, 'is_active' => true])
            ->assertCreated();
    }

    public function test_cannot_delete_status_used_by_project(): void
    {
        $admin    = $this->admin();
        $status   = StatusRule::create(['name' => 'InUse', 'icon' => '▶', 'color' => '#3b82f6', 'sort_order' => 1, 'is_active' => true]);
        $priority = Priority::create(['name' => '中', 'color' => '#f59e0b', 'sort_order' => 2, 'is_active' => true]);
        $category = Category::create(['name' => '開發', 'color' => '#6366f1', 'is_active' => true]);

        Project::create([
            'project_no'  => 'P002',
            'name'        => 'Project',
            'start_date'  => '2026-01-01',
            'due_date'    => '2026-03-31',
            'status_id'   => $status->id,
            'priority_id' => $priority->id,
            'category_id' => $category->id,
            'owner_id'    => $admin->id,
            'created_by'  => $admin->id,
        ]);

        $this->actingAs($admin)
            ->deleteJson("/api/settings/statuses/{$status->id}")
            ->assertStatus(422);
    }

    // ── Priorities ────────────────────────────────────────────────────────

    public function test_admin_can_create_priority(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/settings/priorities', ['name' => 'High', 'color' => '#ef4444', 'sort_order' => 1, 'is_active' => true])
            ->assertCreated();
    }
}
