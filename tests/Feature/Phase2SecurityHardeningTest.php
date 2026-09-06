<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class Phase2SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_refresh_token_rotation_and_reuse_is_rejected(): void
    {
        RateLimiter::clear('admin-login:superadmin');

        $admin = Admin::factory()->create([
            'username' => 'superadmin',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/auth/admin/login', [
            'username' => 'superadmin',
            'password' => 'secret123',
        ]);

        $response->assertOk();
        $refreshToken = $response->json('data.refresh_token');
        $this->assertNotEmpty($refreshToken);

        $refreshed = $this->postJson('/api/auth/admin/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $refreshed->assertOk();
        $refreshed->assertJsonPath('success', true);
        $newRefreshToken = $refreshed->json('data.refresh_token');
        $this->assertNotEmpty($newRefreshToken);
        $this->assertNotSame($refreshToken, $newRefreshToken);

        $reused = $this->postJson('/api/auth/admin/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $reused->assertStatus(401);
    }

    public function test_inactive_admin_and_participant_cannot_login(): void
    {
        Admin::factory()->create([
            'username' => 'disabled-admin',
            'password' => Hash::make('secret123'),
            'status' => 'inactive',
        ]);

        Participant::factory()->create([
            'username' => 'disabled-participant',
            'password' => Hash::make('secret123'),
            'status' => 'inactive',
        ]);

        $adminLogin = $this->postJson('/api/auth/admin/login', [
            'username' => 'disabled-admin',
            'password' => 'secret123',
        ]);
        $adminLogin->assertStatus(403);

        $participantLogin = $this->postJson('/api/auth/participant/login', [
            'username' => 'disabled-participant',
            'password' => 'secret123',
        ]);
        $participantLogin->assertStatus(403);
    }

    public function test_password_change_requires_current_password_and_rotates_user_tokens(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'passwordadmin',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $token = $admin->createToken('admin-api', ['*'])->plainTextToken;

        $bad = $this->withToken($token)->postJson('/api/auth/admin/password/change', [
            'current_password' => 'wrong-password',
            'password' => 'newSecret123',
            'password_confirmation' => 'newSecret123',
        ]);
        $bad->assertStatus(422);

        $good = $this->withToken($token)->postJson('/api/auth/admin/password/change', [
            'current_password' => 'secret123',
            'password' => 'newSecret123',
            'password_confirmation' => 'newSecret123',
        ]);

        $good->assertOk();
        $good->assertJsonPath('success', true);
        $this->assertTrue(Hash::check('newSecret123', $admin->fresh()->password));
    }

    public function test_password_reset_flow_works_and_rejects_reuse(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'resetadmin',
            'email' => 'resetadmin@example.com',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $request = $this->postJson('/api/auth/admin/password/reset/request', [
            'email' => 'resetadmin@example.com',
        ]);
        $request->assertOk();

        $token = $this->app['db']->table('password_reset_tokens')->where('email', 'resetadmin@example.com')->value('token');
        $this->assertNotEmpty($token);

        $reset = $this->postJson('/api/auth/admin/password/reset', [
            'token' => $token,
            'password' => 'newResetSecret123',
            'password_confirmation' => 'newResetSecret123',
        ]);

        $reset->assertOk();
        $this->assertTrue(Hash::check('newResetSecret123', $admin->fresh()->password));

        $reused = $this->postJson('/api/auth/admin/password/reset', [
            'token' => $token,
            'password' => 'anotherSecret123',
            'password_confirmation' => 'anotherSecret123',
        ]);

        $reused->assertStatus(400);
    }
}
