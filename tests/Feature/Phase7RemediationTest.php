<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Financial\Exceptions\ImmutableFinancialRecordException;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Investment;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class Phase7RemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_matrix_pages_are_real_and_exports_are_authorized(): void
    {
        $admin = Admin::factory()->create([
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'role' => 'financial-manager',
            'permissions' => ['reports.view', 'reports.export', 'audit_logs.view'],
        ]);

        $this->withSession(['web_admin_id' => $admin->id]);
        foreach (['participants', 'investments', 'capital', 'monthly-profits', 'annual-profits', 'distribution', 'funds', 'fund-transactions', 'depreciation', 'settlements', 'due-paid', 'capital-growth'] as $report) {
            $this->get('/admin/reports/' . $report)->assertOk();
        }

        $this->get('/admin/reports/monthly-profits/export/excel')->assertOk();
        $this->get('/admin/reports/monthly-profits/export/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');

        $blocked = Admin::factory()->create(['password' => Hash::make('secret123'), 'status' => 'active', 'permissions' => ['reports.view']]);
        $this->withSession(['web_admin_id' => $blocked->id])->get('/admin/reports/monthly-profits/export/excel')->assertForbidden();
    }

    public function test_audit_logs_are_immutable_and_detail_page_is_protected(): void
    {
        $admin = Admin::factory()->create(['password' => Hash::make('secret123'), 'status' => 'active', 'permissions' => ['audit_logs.view']]);
        $audit = AuditLog::query()->create(['auditable_type' => 'test', 'auditable_id' => 1, 'action' => 'test_action', 'actor_type' => 'system', 'actor_id' => 0, 'metadata' => ['safe' => true], 'created_at' => now()]);

        $audit->action = 'changed';
        $this->expectException(ImmutableFinancialRecordException::class);
        $audit->save();
    }

    public function test_investment_approval_creates_a_participant_notification_after_commit(): void
    {
        $admin = Admin::factory()->create(['password' => Hash::make('secret123'), 'status' => 'active']);
        $participant = Participant::factory()->create(['password' => Hash::make('secret123'), 'status' => 'active']);
        $investment = Investment::query()->create([
            'participant_id' => $participant->id,
            'amount' => '100.00',
            'invested_at' => '2026-09-08',
            'status' => 'pending',
        ]);

        app(\App\Actions\Investment\ApproveInvestmentAction::class)->execute($admin, $investment);

        $this->assertDatabaseHas('notifications', [
            'participant_id' => $participant->id,
            'type' => 'investment_update',
            'is_read' => false,
        ]);

        $investment->amount = '125.00';
        $investment->save();

        $this->assertDatabaseHas('notifications', [
            'participant_id' => $participant->id,
            'type' => 'investment_value_update',
        ]);
    }
}
