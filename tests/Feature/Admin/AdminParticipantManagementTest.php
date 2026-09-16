<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AdminParticipantManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::factory()->create([
            'username' => 'participant-manager',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'name' => 'Participant Manager',
            'role' => 'super-admin',
            'is_super_admin' => true,
        ]);
    }

    public function test_forms_require_admin_session(): void
    {
        $participant = Participant::factory()->create();

        $this->get('/admin/participants/create')->assertRedirect('/admin/login');
        $this->get("/admin/participants/{$participant->id}/edit")->assertRedirect('/admin/login');
        $this->get("/admin/participants/{$participant->id}/password")->assertRedirect('/admin/login');
        $this->post('/admin/participants', [])->assertRedirect('/admin/login');
        $this->put("/admin/participants/{$participant->id}", [])->assertRedirect('/admin/login');
        $this->put("/admin/participants/{$participant->id}/password", [])->assertRedirect('/admin/login');
    }

    public function test_admin_can_view_create_form(): void
    {
        $admin = $this->admin();

        $this->withSession(['web_admin_id' => $admin->id])
            ->get('/admin/participants/create')
            ->assertOk()
            ->assertSee('إنشاء حساب مشارك')
            ->assertSee('first_name')
            ->assertSee('password_confirmation');
    }

    public function test_admin_can_create_participant(): void
    {
        $admin = $this->admin();

        $this->withSession(['web_admin_id' => $admin->id])
            ->post('/admin/participants', [
                'first_name' => 'أحمد',
                'last_name' => 'محمد',
                'username' => 'ahmed.mohamed',
                'email' => 'ahmed@example.com',
                'password' => 'StrongPass123',
                'password_confirmation' => 'StrongPass123',
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.participants'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('participants', [
            'username' => 'ahmed.mohamed',
            'email' => 'ahmed@example.com',
            'status' => 'active',
            'created_by_admin_id' => $admin->id,
        ]);

        $participant = Participant::query()->where('username', 'ahmed.mohamed')->firstOrFail();
        $this->assertNotEquals('StrongPass123', $participant->password);
        $this->assertTrue(Hash::check('StrongPass123', $participant->password));

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Participant::class,
            'auditable_id' => $participant->id,
            'action' => 'participant_created',
            'actor_type' => Admin::class,
            'actor_id' => $admin->id,
        ]);
    }

    public function test_create_validates_duplicates_and_password(): void
    {
        $admin = $this->admin();
        Participant::factory()->create(['username' => 'taken.user', 'email' => 'taken@example.com']);

        $this->withSession(['web_admin_id' => $admin->id])
            ->post('/admin/participants', [
                'first_name' => 'أحمد',
                'last_name' => 'محمد',
                'username' => 'taken.user',
                'email' => 'taken@example.com',
                'password' => 'short',
                'password_confirmation' => 'short',
                'status' => 'active',
            ])
            ->assertSessionHasErrors(['username', 'email', 'password']);

        $this->assertDatabaseMissing('participants', ['username' => 'taken.user', 'created_by_admin_id' => $admin->id]);
    }

    public function test_admin_can_edit_and_update_participant(): void
    {
        $admin = $this->admin();
        $participant = Participant::factory()->create([
            'first_name' => 'قبل',
            'username' => 'before.update',
        ]);

        $this->withSession(['web_admin_id' => $admin->id])
            ->get("/admin/participants/{$participant->id}/edit")
            ->assertOk()
            ->assertSee('تعديل بيانات المشارك')
            ->assertSee('before.update');

        $this->withSession(['web_admin_id' => $admin->id])
            ->put("/admin/participants/{$participant->id}", [
                'first_name' => 'بعد',
                'last_name' => 'التحديث',
                'username' => 'after.update',
                'email' => 'after@example.com',
                'status' => 'inactive',
            ])
            ->assertRedirect(route('admin.participants'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('participants', [
            'id' => $participant->id,
            'first_name' => 'بعد',
            'username' => 'after.update',
            'email' => 'after@example.com',
            'status' => 'inactive',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Participant::class,
            'auditable_id' => $participant->id,
            'action' => 'participant_updated',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_update_ignores_own_username_when_unchanged(): void
    {
        $admin = $this->admin();
        $participant = Participant::factory()->create(['username' => 'keep.me']);

        $this->withSession(['web_admin_id' => $admin->id])
            ->put("/admin/participants/{$participant->id}", [
                'first_name' => $participant->first_name,
                'last_name' => $participant->last_name,
                'username' => 'keep.me',
                'email' => null,
                'status' => 'active',
            ])
            ->assertRedirect(route('admin.participants'));
    }

    public function test_admin_can_change_participant_password_and_revoke_tokens(): void
    {
        $admin = $this->admin();
        $participant = Participant::factory()->create(['password' => Hash::make('OldPass123')]);
        $participant->createToken('mobile');
        $this->assertSame(1, $participant->tokens()->count());

        $this->withSession(['web_admin_id' => $admin->id])
            ->get("/admin/participants/{$participant->id}/password")
            ->assertOk()
            ->assertSee('تغيير كلمة المرور');

        $this->withSession(['web_admin_id' => $admin->id])
            ->put("/admin/participants/{$participant->id}/password", [
                'password' => 'NewPass456',
                'password_confirmation' => 'NewPass456',
            ])
            ->assertRedirect(route('admin.participants'))
            ->assertSessionHas('success');

        $participant->refresh();
        $this->assertTrue(Hash::check('NewPass456', $participant->password));
        $this->assertSame(0, $participant->tokens()->count());

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Participant::class,
            'auditable_id' => $participant->id,
            'action' => 'participant_password_changed',
            'actor_id' => $admin->id,
        ]);

        $log = AuditLog::query()->where('action', 'participant_password_changed')->firstOrFail();
        $this->assertStringContainsString('[redacted]', json_encode($log->new_values ?? []));
    }

    public function test_password_change_validates_confirmation(): void
    {
        $admin = $this->admin();
        $participant = Participant::factory()->create();

        $this->withSession(['web_admin_id' => $admin->id])
            ->put("/admin/participants/{$participant->id}/password", [
                'password' => 'NewPass456',
                'password_confirmation' => 'Different456',
            ])
            ->assertSessionHasErrors('password');
    }
}