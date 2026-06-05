<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberApprovalTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(string $code = 'AAAAAAAA'): Company
    {
        $creator = User::factory()->create(['role' => 'admin', 'status' => 'active']);

        return Company::create([
            'name'       => 'TestCo',
            'invite_code' => $code,
            'status'     => 'active',
            'created_by' => $creator->id,
        ]);
    }

    private function boss(?Company $company = null): User
    {
        return User::factory()->create([
            'role'       => 'boss',
            'status'     => 'active',
            'company_id' => $company?->id,
        ]);
    }

    private function member(?Company $company = null): User
    {
        return User::factory()->create([
            'role'       => 'member',
            'status'     => 'active',
            'company_id' => $company?->id,
        ]);
    }

    public function test_boss_can_update_same_company_member_including_job_title(): void
    {
        $company = $this->makeCompany();
        $boss    = $this->boss($company);
        $target  = $this->member($company);

        $this->actingAs($boss)
            ->putJson("/api/manager/members/{$target->id}", [
                'name'      => '王小明',
                'job_title' => '業助',
                'status'    => 'active',
            ])
            ->assertOk()
            ->assertJsonPath('name', '王小明')
            ->assertJsonPath('job_title', '業助');

        $this->assertDatabaseHas('users', [
            'id'        => $target->id,
            'name'      => '王小明',
            'job_title' => '業助',
        ]);
    }

    public function test_boss_cannot_update_member_of_other_company(): void
    {
        $companyA = $this->makeCompany('AAAAAAAA');
        $companyB = $this->makeCompany('BBBBBBBB');
        $boss     = $this->boss($companyA);
        $target   = $this->member($companyB);

        $this->actingAs($boss)
            ->putJson("/api/manager/members/{$target->id}", ['name' => 'Hacked'])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['id' => $target->id, 'name' => 'Hacked']);
    }

    public function test_boss_cannot_promote_member_to_admin(): void
    {
        $company = $this->makeCompany();
        $boss    = $this->boss($company);
        $target  = $this->member($company);

        $this->actingAs($boss)
            ->putJson("/api/manager/members/{$target->id}", ['role' => 'admin'])
            ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'member']);
    }

    public function test_boss_cannot_promote_member_to_boss(): void
    {
        $company = $this->makeCompany();
        $boss    = $this->boss($company);
        $target  = $this->member($company);

        $this->actingAs($boss)
            ->putJson("/api/manager/members/{$target->id}", ['role' => 'boss'])
            ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'member']);
    }

    public function test_boss_can_set_member_role_to_accountant(): void
    {
        $company = $this->makeCompany();
        $boss    = $this->boss($company);
        $target  = $this->member($company);

        $this->actingAs($boss)
            ->putJson("/api/manager/members/{$target->id}", ['role' => 'accountant'])
            ->assertOk()
            ->assertJsonPath('role', 'accountant');

        $this->assertDatabaseHas('users', ['id' => $target->id, 'role' => 'accountant']);
    }

    public function test_boss_cannot_edit_admin_or_boss_user(): void
    {
        $company = $this->makeCompany();
        $boss    = $this->boss($company);
        $otherBoss = $this->boss($company);

        $this->actingAs($boss)
            ->putJson("/api/manager/members/{$otherBoss->id}", ['name' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_member_cannot_use_manager_members_endpoint(): void
    {
        $company = $this->makeCompany();
        $actor   = $this->member($company);
        $target  = $this->member($company);

        $this->actingAs($actor)
            ->putJson("/api/manager/members/{$target->id}", ['name' => 'Nope'])
            ->assertForbidden();
    }

    public function test_admin_can_still_update_user_via_users_endpoint(): void
    {
        $admin  = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $target = $this->member();

        $this->actingAs($admin)
            ->putJson("/api/users/{$target->id}", [
                'name'   => 'Admin Edited',
                'role'   => 'boss',
                'status' => 'active',
            ])
            ->assertOk()
            ->assertJsonPath('name', 'Admin Edited')
            ->assertJsonPath('role', 'boss');
    }

    /** 開通時由管理者直接賦予職稱 */
    public function test_boss_can_assign_job_title_on_approve(): void
    {
        $company = $this->makeCompany();
        $boss    = $this->boss($company);
        $target  = User::factory()->create([
            'role' => 'member', 'status' => 'pending', 'company_id' => $company->id,
        ]);

        $this->actingAs($boss)
            ->postJson("/api/manager/members/{$target->id}/approve", ['job_title' => '美編'])
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $target->id, 'status' => 'active', 'job_title' => '美編',
        ]);
    }

    /** 職稱由管理者指派，本人不可透過 profile 自改 */
    public function test_member_cannot_change_own_job_title_via_profile(): void
    {
        $company = $this->makeCompany();
        $target  = User::factory()->create([
            'role' => 'member', 'status' => 'active',
            'company_id' => $company->id, 'job_title' => '業助',
        ]);

        $this->actingAs($target)
            ->putJson('/api/profile', ['name' => '改名字', 'job_title' => '老闆'])
            ->assertOk()
            ->assertJsonPath('name', '改名字')
            ->assertJsonPath('job_title', '業助'); // 職稱不變

        $this->assertDatabaseHas('users', [
            'id' => $target->id, 'name' => '改名字', 'job_title' => '業助',
        ]);
    }
}
