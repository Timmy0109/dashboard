<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyTest extends TestCase
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

    private function makeCompany(User $creator, string $code = 'AAAAAAAA'): Company
    {
        return Company::create(['name' => 'TestCo', 'invite_code' => $code, 'status' => 'active', 'created_by' => $creator->id]);
    }

    public function test_admin_can_list_companies(): void
    {
        $admin = $this->admin();
        $this->makeCompany($admin);

        $this->actingAs($admin)
            ->getJson('/api/admin/companies')
            ->assertOk()
            ->assertJsonStructure([['id', 'name']]);
    }

    public function test_non_admin_cannot_list_companies(): void
    {
        $this->actingAs($this->member())
            ->getJson('/api/admin/companies')
            ->assertForbidden();
    }

    public function test_admin_can_create_company(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson('/api/admin/companies', ['name' => 'New Corp'])
            ->assertCreated()
            ->assertJsonPath('name', 'New Corp');
    }

    public function test_admin_can_soft_delete_company(): void
    {
        $admin   = $this->admin();
        $company = $this->makeCompany($admin, 'DELDELCO');

        $this->actingAs($admin)
            ->deleteJson("/api/admin/companies/{$company->id}")
            ->assertOk();  // CompanyController::destroy returns 200 with message

        $this->assertSoftDeleted('companies', ['id' => $company->id]);
    }

    public function test_deleting_company_suspends_its_active_users(): void
    {
        $admin   = $this->admin();
        $company = $this->makeCompany($admin, 'SUSPSUSP');
        $member  = User::factory()->create(['role' => 'member', 'status' => 'active', 'company_id' => $company->id]);

        $this->actingAs($admin)->deleteJson("/api/admin/companies/{$company->id}")->assertOk();

        $this->assertDatabaseHas('users', ['id' => $member->id, 'status' => 'suspended', 'suspended_by_company_delete' => 1]);
    }

    public function test_restoring_company_reactivates_suspended_users(): void
    {
        $admin   = $this->admin();
        $company = $this->makeCompany($admin, 'RESTREST');
        $member  = User::factory()->create(['role' => 'member', 'status' => 'active', 'company_id' => $company->id]);

        $company->delete();
        $this->assertDatabaseHas('users', ['id' => $member->id, 'status' => 'suspended']);

        $this->actingAs($admin)->postJson("/api/admin/companies/{$company->id}/restore")->assertOk();
        $this->assertDatabaseHas('users', ['id' => $member->id, 'status' => 'active', 'suspended_by_company_delete' => 0]);
    }

    public function test_admin_can_toggle_feature(): void
    {
        $admin   = $this->admin();
        $company = $this->makeCompany($admin, 'FEATFEAT');
        Feature::create(['key' => 'report.stats_dashboard', 'name' => 'Stats', 'is_default' => false]);

        $this->actingAs($admin)
            ->putJson("/api/admin/companies/{$company->id}/features/report.stats_dashboard", ['enabled' => true])
            ->assertOk();

        $this->assertDatabaseHas('company_features', ['company_id' => $company->id, 'feature_key' => 'report.stats_dashboard', 'enabled' => 1]);
    }

    public function test_admin_can_regenerate_invite_code(): void
    {
        $admin    = $this->admin();
        $company  = $this->makeCompany($admin, 'OLDCODE1');
        $original = $company->invite_code;

        $res = $this->actingAs($admin)
            ->postJson("/api/admin/companies/{$company->id}/invite-code")
            ->assertOk();

        $this->assertNotEquals($original, $res->json('invite_code'));
    }
}
