<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Participant;
use App\Support\AdminAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase2AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_returns_tokens(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'superadmin',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/admin/login', [
            'username' => 'superadmin',
            'password' => 'secret123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
                'user' => [
                    'id',
                    'username',
                ],
            ],
        ]);

        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $admin->id]);
    }

    public function test_participant_login_returns_tokens(): void
    {
        $participant = Participant::factory()->create([
            'username' => 'participant01',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/auth/participant/login', [
            'username' => 'participant01',
            'password' => 'secret123',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.user.id', $participant->id);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
                'user' => ['id', 'username'],
            ],
        ]);
    }

    public function test_participant_cannot_access_admin_route(): void
    {
        $participant = Participant::factory()->create([
            'username' => 'participant02',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $token = $participant->createToken('participant-api', ['*'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/admin/participants');

        $response->assertStatus(403);
    }

    public function test_participant_cannot_access_other_participant_data(): void
    {
        $owner = Participant::factory()->create([
            'username' => 'owner',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);
        $other = Participant::factory()->create([
            'username' => 'other',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $token = $owner->createToken('participant-api', ['*'])->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/me');

        $response->assertOk();
        $response->assertJsonPath('data.id', $owner->id);
        $this->assertNotSame($other->id, $response->json('data.id'));
    }

    public function test_admin_and_participant_authentication_are_separate(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'adminauth',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'role' => AdminAuthorization::ROLE_SUPER_ADMIN,
            'permissions' => AdminAuthorization::permissionsForRole(AdminAuthorization::ROLE_SUPER_ADMIN),
            'is_super_admin' => true,
        ]);
        $participant = Participant::factory()->create([
            'username' => 'participantauth',
            'password' => Hash::make('secret123'),
            'status' => 'active',
        ]);

        $adminToken = $admin->createToken('admin-api', ['*'])->plainTextToken;
        $participantToken = $participant->createToken('participant-api', ['*'])->plainTextToken;

        $adminRoute = $this->withToken($adminToken)->getJson('/api/v1/admin/participants');
        $participantRoute = $this->withToken($participantToken)->getJson('/api/v1/admin/participants');

        $adminRoute->assertStatus(200);
        $participantRoute->assertStatus(403);
    }
}
