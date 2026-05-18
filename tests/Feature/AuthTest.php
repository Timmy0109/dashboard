<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => 'member', 'status' => 'active'], $attrs));
    }

    public function test_login_returns_user_on_valid_credentials(): void
    {
        $user = $this->makeUser(['email' => 'test@example.com']);

        $this->postJson('/api/login', ['email' => 'test@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.email', 'test@example.com');
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $this->makeUser(['email' => 'test@example.com']);

        $this->postJson('/api/login', ['email' => 'test@example.com', 'password' => 'wrong'])
            ->assertStatus(422);
    }

    public function test_login_fails_for_suspended_user(): void
    {
        $this->makeUser(['email' => 'test@example.com', 'status' => 'suspended']);

        $this->postJson('/api/login', ['email' => 'test@example.com', 'password' => 'password'])
            ->assertStatus(422);
    }

    public function test_login_fails_for_pending_user(): void
    {
        $this->makeUser(['email' => 'test@example.com', 'status' => 'pending']);

        $this->postJson('/api/login', ['email' => 'test@example.com', 'password' => 'password'])
            ->assertStatus(422);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->getJson('/api/me')->assertOk()->assertJsonPath('email', $user->email);
    }

    public function test_me_requires_auth(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_logout_invalidates_session(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->postJson('/api/logout')->assertOk();
    }

    public function test_forgot_password_sends_link_for_existing_email(): void
    {
        $user = $this->makeUser(['email' => 'user@example.com']);

        $this->postJson('/api/forgot-password', ['email' => 'user@example.com'])
            ->assertOk()
            ->assertJsonPath('message', '密碼重設連結已寄出，請查看您的信箱');
    }

    public function test_forgot_password_returns_error_for_unknown_email(): void
    {
        $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com'])
            ->assertStatus(422);
    }
}
