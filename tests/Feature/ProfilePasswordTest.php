<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfilePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create([
            'role'     => 'member',
            'status'   => 'active',
            'password' => Hash::make('oldpass12'),
        ]);
    }

    public function test_user_can_update_password_with_correct_current_password(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->putJson('/api/profile/password', [
                'current_password'      => 'oldpass12',
                'password'              => 'newpass34',
                'password_confirmation' => 'newpass34',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('newpass34', $user->fresh()->password));
    }

    public function test_update_fails_when_current_password_wrong(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->putJson('/api/profile/password', [
                'current_password'      => 'wrongpass',
                'password'              => 'newpass34',
                'password_confirmation' => 'newpass34',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');
    }

    public function test_update_fails_when_confirmation_mismatches(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->putJson('/api/profile/password', [
                'current_password'      => 'oldpass12',
                'password'              => 'newpass34',
                'password_confirmation' => 'different',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_update_fails_when_password_too_short(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->putJson('/api/profile/password', [
                'current_password'      => 'oldpass12',
                'password'              => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_guests_cannot_update_password(): void
    {
        $this->putJson('/api/profile/password', [
            'current_password'      => 'oldpass12',
            'password'              => 'newpass34',
            'password_confirmation' => 'newpass34',
        ])->assertStatus(401);
    }
}
