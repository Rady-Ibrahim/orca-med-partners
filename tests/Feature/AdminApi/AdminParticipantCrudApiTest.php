<?php

declare(strict_types=1);

namespace Tests\Feature\AdminApi;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Investment;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminParticipantCrudApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->create([
            'username' => 'crud-admin',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'is_super_admin' => true,
        ]);
    }

    public function test_store_creates_participant_with_hashed_password_role_and_audit(): void
    {
        $token = $this->admin()->createToken('admin-api', ['*'])->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/admin/participants', [
            'first_name' => 'New',
            'last_name' => 'Participant',
            'username' => 'new.participant',
            'code' => 'PC-NEW-01',
            'email' => 'new@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'status' => 'active',
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        $participant = Participant::query()->where('username', 'new.participant')->firstOrFail();

        self::assertTrue(Hash::check('secret123', $participant->password), 'Password must be stored hashed.');
        self::assertSame('participant', $participant->role);
        self::assertSame('active', $participant->status);
        self::assertSame('PC-NEW-01', $participant->code);
        self::assertDatabaseHas('audit_logs', ['action' => 'participant_created']);
    }

    public function test_store_rejects_duplicate_username_and_weak_password(): void
    {
        $token = $this->admin()->createToken('admin-api', ['*'])->plainTextToken;
        Participant::factory()->create(['username' => 'taken.name']);

        $this->withToken($token)
            ->postJson('/api/v1/admin/participants', [
                'first_name' => 'A', 'last_name' => 'B', 'username' => 'taken.name', 'code' => 'PC-TAKEN',
                'password' => 'secret123', 'password_confirmation' => 'secret123', 'status' => 'active',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->withToken($token)
            ->postJson('/api/v1/admin/participants', [
                'first_name' => 'A', 'last_name' => 'B', 'username' => 'fresh.name', 'code' => 'PC-FRESH',
                'password' => 'short', 'password_confirmation' => 'short', 'status' => 'active',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_update_changes_profile_fields_and_audits(): void
    {
        $token = $this->admin()->createToken('admin-api', ['*'])->plainTextToken;
        $participant = Participant::factory()->create(['username' => 'update.me', 'status' => 'active']);

        $this->withToken($token)
            ->putJson("/api/v1/admin/participants/{$participant->id}", [
                'first_name' => 'Renamed',
                'last_name' => 'Surname',
                'username' => 'update.me',
                'code' => 'PC-UPDATED-01',
                'status' => 'inactive',
            ])
            ->assertOk()
            ->assertJsonPath('data.first_name', 'Renamed')
            ->assertJsonPath('data.code', 'PC-UPDATED-01')
            ->assertJsonPath('data.status', 'inactive');

        self::assertDatabaseHas('audit_logs', ['action' => 'participant_updated']);
    }

    public function test_participants_can_be_searched_by_code_via_api(): void
    {
        $token = $this->admin()->createToken('admin-api', ['*'])->plainTextToken;
        Participant::factory()->create(['username' => 'code.search.target', 'code' => 'PC-SKY-77']);

        $this->withToken($token)
            ->getJson('/api/v1/admin/participants?search=PC-SKY-77')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.code', 'PC-SKY-77');
    }

    public function test_store_rejects_duplicate_code(): void
    {
        $token = $this->admin()->createToken('admin-api', ['*'])->plainTextToken;
        Participant::factory()->create(['username' => 'holder.user', 'code' => 'PC-DUP-01']);

        $this->withToken($token)
            ->postJson('/api/v1/admin/participants', [
                'first_name' => 'Dupe',
                'last_name' => 'Code',
                'username' => 'dupe.code',
                'code' => 'PC-DUP-01',
                'password' => 'secret123',
                'password_confirmation' => 'secret123',
                'status' => 'active',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_destroy_soft_deletes_clean_participant(): void
    {
        $token = $this->admin()->createToken('admin-api', ['*'])->plainTextToken;
        $participant = Participant::factory()->create(['username' => 'clean.user']);

        $this->withToken($token)
            ->deleteJson("/api/v1/admin/participants/{$participant->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('participants', ['id' => $participant->id]);
        self::assertDatabaseHas('audit_logs', ['action' => 'participant_deleted']);
    }

    public function test_destroy_with_financial_history_is_blocked(): void
    {
        $admin = $this->admin();
        $token = $admin->createToken('admin-api', ['*'])->plainTextToken;
        $participant = Participant::factory()->create(['username' => 'investor.user']);

        Investment::query()->create([
            'participant_id' => $participant->id,
            'amount' => '10000.00',
            'invested_at' => '2026-01-01',
            'status' => 'pending',
            'created_by_admin_id' => $admin->id,
        ]);

        $this->withToken($token)
            ->deleteJson("/api/v1/admin/participants/{$participant->id}")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        self::assertDatabaseHas('participants', ['id' => $participant->id, 'deleted_at' => null]);
        self::assertDatabaseMissing('audit_logs', ['action' => 'participant_deleted']);
    }

    public function test_employee_without_permission_cannot_create_participants(): void
    {
        $employee = Admin::factory()->create([
            'username' => 'limited-crud',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'is_super_admin' => false,
            'permissions' => ['participants.view'],
        ]);
        $token = $employee->createToken('admin-api', ['*'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/admin/participants', [
                'first_name' => 'A', 'last_name' => 'B', 'username' => 'no.create', 'code' => 'PC-NO403',
                'password' => 'secret123', 'password_confirmation' => 'secret123', 'status' => 'active',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_create_investment_via_api(): void
    {
        $token = $this->admin()->createToken('admin-api', ['*'])->plainTextToken;
        $participant = Participant::factory()->create();

        $response = $this->withToken($token)->postJson('/api/v1/admin/investments', [
            'participant_id' => $participant->id,
            'amount' => '25000.00',
            'invested_at' => '2026-05-15',
            'notes' => 'Initial position',
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
        self::assertSame('25000.00', $response->json('data.amount'));
        self::assertSame('pending', $response->json('data.status'));
        self::assertDatabaseHas('investments', [
            'participant_id' => $participant->id,
            'amount' => '25000.00',
            'status' => 'pending',
        ]);
        self::assertDatabaseHas('audit_logs', ['action' => 'investment_created']);
    }

    public function test_investment_creation_validates_amount(): void
    {
        $token = $this->admin()->createToken('admin-api', ['*'])->plainTextToken;
        $participant = Participant::factory()->create();

        $this->withToken($token)
            ->postJson('/api/v1/admin/investments', ['participant_id' => $participant->id, 'amount' => '0.001'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_investment_approval_is_idempotent_and_audited(): void
    {
        $admin = $this->admin();
        $token = $admin->createToken('admin-api', ['*'])->plainTextToken;
        $participant = Participant::factory()->create();
        $investment = Investment::query()->create([
            'participant_id' => $participant->id,
            'amount' => '5000.00',
            'invested_at' => '2026-06-01',
            'status' => 'pending',
            'created_by_admin_id' => $admin->id,
        ]);

        $this->withToken($token)
            ->postJson("/api/v1/admin/investments/{$investment->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        self::assertDatabaseHas('audit_logs', ['action' => 'investment_approved']);
        self::assertSame(1, Investment::query()->where('id', $investment->id)->where('status', 'approved')->count());

        $this->withToken($token)
            ->postJson("/api/v1/admin/investments/{$investment->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        self::assertSame(1, Investment::query()->where('id', $investment->id)->where('status', 'approved')->count());
        self::assertSame(1, AuditLog::query()->where('action', 'investment_approved')->where('auditable_id', $investment->id)->count());
    }
}
