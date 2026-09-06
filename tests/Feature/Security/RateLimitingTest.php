<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Admin;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_is_rate_limited_after_ten_failed_attempts(): void
    {
        Admin::factory()->create([
            'username' => 'rate-admin',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $server = ['REMOTE_ADDR' => '203.0.113.11'];

        for ($i = 0; $i < 10; $i++) {
            $this->withServerVariables($server)
                ->postJson('/api/auth/admin/login', [
                    'username' => 'rate-admin',
                    'password' => 'wrong-password',
                ])
                ->assertStatus(401);
        }

        $this->withServerVariables($server)
            ->postJson('/api/auth/admin/login', [
                'username' => 'rate-admin',
                'password' => 'wrong-password',
            ])
            ->assertStatus(429);
    }

    public function test_participant_login_is_rate_limited_after_ten_failed_attempts(): void
    {
        Participant::factory()->create([
            'username' => 'rate-participant',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $server = ['REMOTE_ADDR' => '203.0.113.12'];

        for ($i = 0; $i < 10; $i++) {
            $this->withServerVariables($server)
                ->postJson('/api/auth/participant/login', [
                    'username' => 'rate-participant',
                    'password' => 'wrong-password',
                ])
                ->assertStatus(401);
        }

        $this->withServerVariables($server)
            ->postJson('/api/auth/participant/login', [
                'username' => 'rate-participant',
                'password' => 'wrong-password',
            ])
            ->assertStatus(429);
    }

    public function test_password_reset_is_rate_limited(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'reset-rate-admin',
            'email' => 'reset-rate-admin@example.com',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $server = ['REMOTE_ADDR' => '203.0.113.13'];

        for ($i = 0; $i < 10; $i++) {
            $this->withServerVariables($server)
                ->postJson('/api/auth/admin/password/reset/request', [
                    'email' => $admin->email,
                ])
                ->assertOk();
        }

        $this->withServerVariables($server)
            ->postJson('/api/auth/admin/password/reset/request', [
                'email' => $admin->email,
            ])
            ->assertStatus(429);
    }

    public function test_refresh_token_endpoint_is_rate_limited(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'refresh-rate-admin',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $server = ['REMOTE_ADDR' => '203.0.113.14'];

        for ($i = 0; $i < 10; $i++) {
            $this->withServerVariables($server)
                ->postJson('/api/auth/admin/refresh', [
                    'refresh_token' => 'invalid-token-' . $i,
                ])
                ->assertStatus(401);
        }

        $this->withServerVariables($server)
            ->postJson('/api/auth/admin/refresh', [
                'refresh_token' => 'invalid-token-fail',
            ])
            ->assertStatus(429);
    }
}
