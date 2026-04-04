<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_token_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $this->postJson(route('auth.login'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'device_name' => 'phpunit',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'test@example.com')
            ->assertJsonMissing(['password']);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $this->postJson(route('auth.login'), [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
            'device_name' => 'phpunit',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $this->postJson(route('auth.login'), [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
            'device_name' => 'phpunit',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_login_validates_required_fields(): void
    {
        $this->postJson(route('auth.login'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password', 'device_name']);
    }

    public function test_login_validates_email_format(): void
    {
        $this->postJson(route('auth.login'), [
            'email' => 'not-an-email',
            'password' => 'password',
            'device_name' => 'phpunit',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson(route('auth.logout'))
            ->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        $this->assertCount(0, $user->tokens);
    }

    public function test_logout_requires_authentication(): void
    {
        $this->postJson(route('auth.logout'))
            ->assertUnauthorized();
    }

    public function test_logout_only_revokes_current_token(): void
    {
        $user = User::factory()->create();
        $user->createToken('device-1');
        $user->createToken('device-2');

        Sanctum::actingAs($user);

        $this->postJson(route('auth.logout'))
            ->assertOk();

        $this->assertCount(2, $user->fresh()->tokens);
    }
}
