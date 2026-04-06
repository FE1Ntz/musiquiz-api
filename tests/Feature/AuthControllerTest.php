<?php

namespace Tests\Feature;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── Login ───────────────────────────────────────────────

    public function test_login_returns_access_and_refresh_tokens(): void
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
            ->assertJsonStructure(['token', 'refresh_token', 'expires_in', 'user'])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'test@example.com')
            ->assertJsonMissing(['password']);
    }

    public function test_login_creates_refresh_token_in_database(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson(route('auth.login'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('refresh_tokens', [
            'user_id' => $user->id,
            'device_name' => 'phpunit',
        ]);
    }

    public function test_login_returns_expires_in_seconds(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $this->postJson(route('auth.login'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'device_name' => 'phpunit',
        ])
            ->assertOk()
            ->assertJsonPath('expires_in', 3600);
    }

    public function test_login_revokes_old_refresh_tokens_for_same_device(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        // First login
        $this->postJson(route('auth.login'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'device_name' => 'phpunit',
        ])->assertOk();

        $this->assertCount(1, $user->refreshTokens);

        // Second login same device
        $this->postJson(route('auth.login'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'device_name' => 'phpunit',
        ])->assertOk();

        $this->assertCount(1, $user->fresh()->refreshTokens);
    }

    public function test_login_keeps_refresh_tokens_for_different_devices(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $this->postJson(route('auth.login'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'device_name' => 'iphone',
        ])->assertOk();

        $this->postJson(route('auth.login'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'device_name' => 'android',
        ])->assertOk();

        $this->assertCount(2, $user->fresh()->refreshTokens);
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

    // ─── Refresh ─────────────────────────────────────────────

    public function test_refresh_issues_new_token_pair(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $loginResponse = $this->postJson(route('auth.login'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'device_name' => 'phpunit',
        ]);

        $refreshToken = $loginResponse->json('refresh_token');

        $this->postJson(route('auth.refresh'), [
            'refresh_token' => $refreshToken,
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'refresh_token', 'expires_in', 'user'])
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_refresh_rotates_refresh_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $loginResponse = $this->postJson(route('auth.login'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'device_name' => 'phpunit',
        ]);

        $oldRefreshToken = $loginResponse->json('refresh_token');

        $refreshResponse = $this->postJson(route('auth.refresh'), [
            'refresh_token' => $oldRefreshToken,
        ]);

        $newRefreshToken = $refreshResponse->json('refresh_token');

        // Old and new should differ
        $this->assertNotEquals($oldRefreshToken, $newRefreshToken);

        // Only 1 refresh token should exist for the user
        $this->assertCount(1, $user->fresh()->refreshTokens);
    }

    public function test_refresh_revokes_old_refresh_token(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $loginResponse = $this->postJson(route('auth.login'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'device_name' => 'phpunit',
        ]);

        $refreshToken = $loginResponse->json('refresh_token');

        // First refresh — should work
        $this->postJson(route('auth.refresh'), [
            'refresh_token' => $refreshToken,
        ])->assertOk();

        // Reuse same token — should fail
        $this->postJson(route('auth.refresh'), [
            'refresh_token' => $refreshToken,
        ])->assertUnauthorized();
    }

    public function test_refresh_fails_with_invalid_token(): void
    {
        $this->postJson(route('auth.refresh'), [
            'refresh_token' => 'totally-invalid-token',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid or expired refresh token.');
    }

    public function test_refresh_fails_with_expired_token(): void
    {
        $user = User::factory()->create();
        $plainToken = Str::random(64);

        RefreshToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $plainToken),
            'device_name' => 'phpunit',
            'expires_at' => now()->subDay(),
        ]);

        $this->postJson(route('auth.refresh'), [
            'refresh_token' => $plainToken,
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Invalid or expired refresh token.');

        // Expired token should be deleted
        $this->assertCount(0, $user->fresh()->refreshTokens);
    }

    public function test_refresh_validates_required_field(): void
    {
        $this->postJson(route('auth.refresh'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('refresh_token');
    }

    // ─── Logout ──────────────────────────────────────────────

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

    public function test_logout_revokes_refresh_token_when_provided(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $loginResponse = $this->postJson(route('auth.login'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'device_name' => 'phpunit',
        ]);

        $refreshToken = $loginResponse->json('refresh_token');
        $accessToken = $loginResponse->json('token');

        $this->withToken($accessToken)
            ->postJson(route('auth.logout'), [
                'refresh_token' => $refreshToken,
            ])
            ->assertOk();

        $this->assertCount(0, $user->fresh()->refreshTokens);
    }

    public function test_logout_keeps_refresh_token_when_not_provided(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password',
        ]);

        $loginResponse = $this->postJson(route('auth.login'), [
            'email' => 'test@example.com',
            'password' => 'password',
            'device_name' => 'phpunit',
        ]);

        $accessToken = $loginResponse->json('token');

        $this->withToken($accessToken)
            ->postJson(route('auth.logout'))
            ->assertOk();

        // Refresh token should still exist
        $this->assertCount(1, $user->fresh()->refreshTokens);
    }
}
