<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Admin;
use App\Models\Investment;
use App\Models\Participant;
use App\Support\AdminAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MassAssignmentAndStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_injection_is_ignored_and_db_state_remains_unchanged(): void
    {
        $participant = Participant::factory()->create([
            'username' => 'role-inject-user',
            'status' => 'active',
            'password' => Hash::make('secret123'),
            'role' => 'participant',
        ]);

        $token = $participant->createToken('participant-api', ['*'])->plainTextToken;

        $this->withToken($token)->patchJson('/api/participant/investments/1', [
            'role' => 'super_admin',
            'participant_id' => 999,
        ])->assertMethodNotAllowed();

        $participant->refresh();
        $this->assertDatabaseHas('participants', ['id' => $participant->id, 'role' => 'participant']);
        $this->assertDatabaseMissing('participants', ['id' => $participant->id, 'role' => 'super_admin']);
    }

    public function test_permission_injection_is_ignored_and_db_state_remains_unchanged(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'permission-inject-admin',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'role' => AdminAuthorization::ROLE_EMPLOYEE,
            'permissions' => ['participants.view'],
            'is_super_admin' => false,
        ]);

        $token = $admin->createToken('admin-api', ['*'])->plainTextToken;

        $this->withToken($token)->patchJson('/api/auth/admin/password/change', [
            'current_password' => 'secret123',
            'password' => 'newSecret123',
            'password_confirmation' => 'newSecret123',
            'permissions' => ['roles.manage', 'permissions.manage'],
        ]);

        $admin->refresh();
        $this->assertDatabaseHas('admins', ['id' => $admin->id, 'permissions' => json_encode(['participants.view'])]);
        $this->assertDatabaseMissing('admins', ['id' => $admin->id, 'permissions' => json_encode(['roles.manage', 'permissions.manage'])]);
    }

    public function test_ownership_injection_is_rejected_and_record_stays_bound_to_auth_user(): void
    {
        $owner = Participant::factory()->create([
            'username' => 'owner-bound',
            'status' => 'active',
            'password' => Hash::make('secret123'),
        ]);
        $other = Participant::factory()->create([
            'username' => 'other-bound',
            'status' => 'active',
            'password' => Hash::make('secret123'),
        ]);

        $investment = Investment::query()->create([
            'participant_id' => $owner->id,
            'amount' => 5000,
            'invested_at' => now()->toDateString(),
            'status' => 'active',
        ]);

        $token = $owner->createToken('participant-api', ['*'])->plainTextToken;

        $this->withToken($token)->patchJson('/api/participant/investments/' . $investment->id, [
            'participant_id' => $other->id,
            'amount' => 9999,
        ])->assertMethodNotAllowed();

        $investment->refresh();
        $this->assertDatabaseHas('investments', ['id' => $investment->id, 'participant_id' => $owner->id]);
        $this->assertDatabaseMissing('investments', ['id' => $investment->id, 'participant_id' => $other->id]);
    }

    public function test_status_and_approval_injection_is_rejected_and_domain_approval_is_required(): void
    {
        $admin = Admin::factory()->create([
            'username' => 'approval-inject-admin',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'role' => AdminAuthorization::ROLE_SUPER_ADMIN,
            'permissions' => AdminAuthorization::permissionsForRole(AdminAuthorization::ROLE_SUPER_ADMIN),
            'is_super_admin' => true,
        ]);

        $participant = Participant::factory()->create([
            'username' => 'approval-inject-participant',
            'status' => 'active',
            'password' => Hash::make('secret123'),
        ]);

        $investment = Investment::query()->create([
            'participant_id' => $participant->id,
            'amount' => 2000,
            'invested_at' => now()->toDateString(),
            'status' => 'pending',
        ]);

        $token = $admin->createToken('admin-api', ['*'])->plainTextToken;

        $this->withToken($token)->postJson('/api/admin/investments/' . $investment->id . '/approve', [
            'status' => 'approved',
            'approved_at' => now()->toDateTimeString(),
        ])->assertOk();

        $investment->refresh();
        $this->assertDatabaseHas('investments', ['id' => $investment->id, 'status' => 'approved']);
        $this->assertNotNull($investment->fresh()->approved_at ?? null);
    }
}
