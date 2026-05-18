<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyFeature;
use App\Models\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(string $code = 'ABCD1234'): Company
    {
        $creator = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        return Company::create([
            'name'        => 'TestCo',
            'invite_code' => $code,
            'status'      => 'active',
            'created_by'  => $creator->id,
        ]);
    }

    public function test_validate_code_returns_company_for_valid_code(): void
    {
        $this->makeCompany('VALID123');

        $this->postJson('/api/register/validate-code', ['invite_code' => 'VALID123'])
            ->assertOk()
            ->assertJsonPath('company_name', 'TestCo');
    }

    public function test_validate_code_fails_for_invalid_code(): void
    {
        $this->postJson('/api/register/validate-code', ['invite_code' => 'BADCODE1'])
            ->assertStatus(422);
    }

    public function test_register_creates_pending_user_when_approval_required(): void
    {
        $company = $this->makeCompany('REGIST12');
        $feature = Feature::create(['key' => 'member.approval_required', 'name' => 'Approval', 'is_default' => false]);
        CompanyFeature::create(['company_id' => $company->id, 'feature_key' => $feature->key, 'enabled' => true]);

        $this->postJson('/api/register', [
            'name'                  => 'New User',
            'email'                 => 'new@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'invite_code'           => 'REGIST12',
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'status' => 'pending']);
    }

    public function test_register_creates_active_user_without_approval_flag(): void
    {
        $this->makeCompany('ACTIVE12');

        $this->postJson('/api/register', [
            'name'                  => 'Active User',
            'email'                 => 'active@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'invite_code'           => 'ACTIVE12',
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'active@example.com', 'status' => 'active']);
    }

    public function test_register_fails_for_duplicate_email(): void
    {
        $this->makeCompany('DUPCODE1');
        User::factory()->create(['email' => 'dup@example.com']);

        $this->postJson('/api/register', [
            'name'                  => 'Dup',
            'email'                 => 'dup@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'invite_code'           => 'DUPCODE1',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_register_fails_for_expired_invite_code(): void
    {
        $creator = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        Company::create([
            'name'                   => 'ExpiredCo',
            'invite_code'            => 'EXPIRED1',
            'invite_code_expires_at' => now()->subDay(),
            'status'                 => 'active',
            'created_by'             => $creator->id,
        ]);

        $this->postJson('/api/register', [
            'name'                  => 'Too Late',
            'email'                 => 'late@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'invite_code'           => 'EXPIRED1',
        ])->assertStatus(422);
    }
}
