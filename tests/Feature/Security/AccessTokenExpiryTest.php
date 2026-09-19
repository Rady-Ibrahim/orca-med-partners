<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Admin;
use App\Models\Participant;
use App\Models\RefreshToken;
use App\Services\RefreshTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AccessTokenExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_participant_token_is_rejected_and_deleted(): void
    {
        $participant = Participant::factory()->create(['status' => 'active']);
        $token = $participant->createToken('participant-api', ['*']);
        $token->accessToken->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Access token expired or account is inactive.');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_expired_admin_token_is_rejected(): void
    {
        $admin = $this->superAdmin();
        $token = $admin->createToken('admin-api', ['*']);
        $token->accessToken->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/admin/participants')
            ->assertStatus(401);
    }

    public function test_inactive_participant_token_is_rejected_and_deleted(): void
    {
        $participant = Participant::factory()->create(['status' => 'inactive']);
        $token = $participant->createToken('participant-api', ['*']);

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/me')
            ->assertStatus(401);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_inactive_admin_token_is_rejected(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'inactive.admin',
            'status' => 'inactive',
            'password' => Hash::make('secret123'),
            'is_super_admin' => true,
        ]);
        $token = $admin->createToken('admin-api', ['*']);

        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/admin/participants')
            ->assertStatus(401);
    }

    public function test_unexpired_active_tokens_still_work(): void
    {
        $participant = Participant::factory()->create(['status' => 'active']);
        $admin = $this->superAdmin();

        $token = $participant->createToken('participant-api', ['*']);
        $adminToken = $admin->createToken('admin-api', ['*']);

        $this->withToken($token->plainTextToken)->getJson('/api/v1/me')->assertOk();
        $this->withToken($adminToken->plainTextToken)->getJson('/api/v1/admin/participants')->assertOk();
    }

    public function test_refresh_for_inactive_participant_returns_403_and_revokes_family(): void
    {
        $participant = Participant::factory()->create(['status' => 'inactive']);

        $raw = app(RefreshTokenService::class)->issue($participant, 'participant-api');

        $this->postJson('/api/v1/auth/participant/refresh', ['refresh_token' => $raw])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Account is inactive.');

        $this->assertDatabaseMissing('refresh_tokens', [
            'tokenable_id' => $participant->id,
            'revoked_at' => null,
        ]);
        $this->assertDatabaseCount('refresh_tokens', 1);
        $this->assertNotNull(RefreshToken::query()->first()->revoked_at);
    }

    public function test_login_response_exposes_expiry_from_configuration(): void
    {
        $participant = Participant::factory()->create([
            'username' => 'expiry.user',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $this->postJson('/api/v1/auth/participant/login', [
            'username' => 'expiry.user',
            'password' => 'secret123',
        ])->assertOk()->assertJsonPath('data.expires_in', (int) config('sanctum.expiration', 60) * 60);
    }

    public function test_reset_password_rejects_expired_token_and_cleans_up(): void
    {
        $participant = Participant::factory()->create(['email' => 'expiry@example.com']);

        $token = hash_hmac('sha256', 'reset-token-value', (string) config('app.key'));
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->insert([
            'email' => $participant->email,
            'token' => $token,
            'created_at' => now()->subHours(2),
        ]);

        $this->postJson('/api/v1/auth/participant/password/reset', [
            'token' => $token,
            'password' => 'NewPass123',
            'password_confirmation' => 'NewPass123',
        ])->assertStatus(400);

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $participant->email]);
    }

    private function superAdmin(): Admin
    {
        return Admin::factory()->create([
            'username' => 'root.token',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'is_super_admin' => true,
        ]);
    }
}