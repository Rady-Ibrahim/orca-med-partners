<?php

declare(strict_types=1);

namespace Tests\Feature\Financial;

use App\Actions\Financial\ApproveMonthlyProfitAction;
use App\Actions\Financial\CreateMonthlyProfitAction;
use App\Actions\Settlements\CreateAnnualSettlementAction;
use App\Domain\Financial\Services\CapitalCalculatorService;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\CapitalSnapshot;
use App\Models\CapitalSnapshotItem;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\Participant;
use App\Models\ParticipantProfitAllocation;
use App\Models\SettlementItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class CapitalOwnershipRatioSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_one_partners_capital_from_the_capital_page_updates_everyones_ratio(): void
    {
        $admin = $this->admin();
        $snapshot = $this->snapshot($admin, [
            '51000000.00',
            '7000000.00',
            '6000000.00',
            '2000000.00',
            '1000000.00',
            '0.00',
        ]);
        $ids = $this->participantIds($snapshot);

        // The last partner is funded on the capital page only, with no backing
        // row in the investments table.
        $this->withSession(['web_admin_id' => $admin->id])
            ->patchJson("/admin/capital/{$snapshot->id}", [
                'items' => [
                    ['participant_id' => $ids[0], 'capital' => '51000000.00'],
                    ['participant_id' => $ids[1], 'capital' => '7000000.00'],
                    ['participant_id' => $ids[2], 'capital' => '6000000.00'],
                    ['participant_id' => $ids[3], 'capital' => '2000000.00'],
                    ['participant_id' => $ids[4], 'capital' => '1000000.00'],
                    ['participant_id' => $ids[5], 'capital' => '1000000.00'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $ratios = $this->ratios($snapshot);

        self::assertSame('68000000.00', (string) $snapshot->fresh()->total_capital);
        self::assertSame('0.0147', $ratios[$ids[5]], 'The funded partner must no longer be a zero-share partner.');
        self::assertSame('0.7501', $ratios[$ids[0]], 'Existing partners must be re-based, not left stale.');
        self::assertSame('0.1029', $ratios[$ids[1]]);
        self::assertSame('0.0882', $ratios[$ids[2]]);
        self::assertSame('0.0294', $ratios[$ids[3]]);
        self::assertSame('0.0147', $ratios[$ids[4]]);
        self::assertSame('1.0000', $this->sum($ratios));
    }

    public function test_capital_page_edit_does_not_require_an_investment_record(): void
    {
        $admin = $this->admin();
        $snapshot = $this->snapshot($admin, ['1000.00', '0.00']);
        $ids = $this->participantIds($snapshot);

        self::assertDatabaseCount('investments', 0);

        $this->withSession(['web_admin_id' => $admin->id])
            ->patchJson("/admin/capital/{$snapshot->id}", [
                'items' => [
                    ['participant_id' => $ids[0], 'capital' => '1000.00'],
                    ['participant_id' => $ids[1], 'capital' => '2500.00'],
                ],
            ])
            ->assertOk();

        // Capital is authoritative on its own, not a projection of investments.
        self::assertDatabaseCount('investments', 0);
        self::assertSame('3500.00', (string) $snapshot->fresh()->total_capital);
        self::assertSame('0.2857', $this->ratios($snapshot)[$ids[0]]);
        self::assertSame('0.7143', $this->ratios($snapshot)[$ids[1]]);
        self::assertSame('1.0000', $this->sum($this->ratios($snapshot)));
    }

    public function test_capital_page_edit_keeps_snapshot_item_ids_stable(): void
    {
        $admin = $this->admin();
        $snapshot = $this->snapshot($admin, ['600.00', '400.00']);
        $ids = $this->participantIds($snapshot);
        $itemIds = $this->itemIds($snapshot);

        $this->withSession(['web_admin_id' => $admin->id])
            ->patchJson("/admin/capital/{$snapshot->id}", [
                'items' => [
                    ['participant_id' => $ids[0], 'capital' => '700.00'],
                    ['participant_id' => $ids[1], 'capital' => '300.00'],
                ],
            ])
            ->assertOk();

        self::assertSame(
            $itemIds,
            $this->itemIds($snapshot),
            'Audit references to capital_snapshot_item must survive a capital page edit.',
        );
    }

    public function test_participants_omitted_from_a_capital_page_edit_keep_their_capital_and_are_rebased(): void
    {
        $admin = $this->admin();
        $snapshot = $this->snapshot($admin, ['600.00', '400.00']);
        $ids = $this->participantIds($snapshot);

        $this->withSession(['web_admin_id' => $admin->id])
            ->patchJson("/admin/capital/{$snapshot->id}", [
                'items' => [
                    ['participant_id' => $ids[0], 'capital' => '900.00'],
                ],
            ])
            ->assertOk();

        $retained = CapitalSnapshotItem::query()
            ->where('capital_snapshot_id', $snapshot->id)
            ->where('participant_id', $ids[1])
            ->firstOrFail();

        self::assertSame('400.00', (string) $retained->participant_capital_snapshot);
        self::assertSame('1300.00', (string) $snapshot->fresh()->total_capital);
        self::assertSame('0.6923', $this->ratios($snapshot)[$ids[0]]);
        self::assertSame('0.3077', $this->ratios($snapshot)[$ids[1]]);
        self::assertSame('1.0000', $this->sum($this->ratios($snapshot)));
    }

    public function test_header_total_always_equals_the_sum_of_item_capitals(): void
    {
        $admin = $this->admin();
        $snapshot = $this->snapshot($admin, ['600.00', '400.00', '0.00']);
        $ids = $this->participantIds($snapshot);

        // A partial payload is the dangerous case: the header must not be
        // narrowed to the submitted rows while the others are retained.
        $this->withSession(['web_admin_id' => $admin->id])
            ->patchJson("/admin/capital/{$snapshot->id}", [
                'items' => [
                    ['participant_id' => $ids[0], 'capital' => '125.55'],
                ],
            ])
            ->assertOk();

        $itemSum = (string) $snapshot->fresh()->items()->get()->reduce(
            static fn (string $carry, CapitalSnapshotItem $item): string => bcadd($carry, (string) $item->participant_capital_snapshot, 2),
            '0.00',
        );

        self::assertSame($itemSum, (string) $snapshot->fresh()->total_capital);
        self::assertSame('1.0000', $this->sum($this->ratios($snapshot)));
    }

    public function test_admin_api_capital_edit_keeps_ratios_consistent_with_distributed_profit(): void
    {
        $admin = $this->admin();
        $token = $admin->createToken('admin-token')->plainTextToken;
        $snapshot = $this->snapshot($admin, ['9000.00', '1000.00']);
        $ids = $this->participantIds($snapshot);
        $rule = $this->rule($admin);

        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '10000.00', 2026, 5);
        app(ApproveMonthlyProfitAction::class)->execute($admin, $profit);

        $this->withToken($token)
            ->patchJson("/api/v1/admin/capital/{$snapshot->id}", [
                'items' => [
                    ['participant_id' => $ids[0], 'capital' => '5000.00'],
                    ['participant_id' => $ids[1], 'capital' => '5000.00'],
                ],
            ])
            ->assertOk();

        $this->artisan('finance:reconcile', ['--year' => 2026])->assertExitCode(0);

        $ratios = $this->ratios($snapshot);
        $allocations = ParticipantProfitAllocation::query()
            ->where('monthly_profit_id', $profit->id)
            ->orderBy('participant_id')
            ->get()
            ->keyBy('participant_id');

        self::assertSame('0.5000', $ratios[$ids[0]]);
        self::assertSame('0.5000', $ratios[$ids[1]]);
        self::assertSame('1.0000', $this->sum($ratios));

        // The ratio shown on the capital page must equal the share ratio the
        // distribution engine pays out, or the two can never be reconciled.
        foreach ($ids as $participantId) {
            self::assertSame(
                $ratios[$participantId],
                (string) $allocations[$participantId]->share_ratio,
                'Capital page ratio and distributed share ratio must not diverge.',
            );
            self::assertSame('3250.00', (string) $allocations[$participantId]->amount);
        }
    }

    public function test_reconcile_repairs_stale_snapshot_ratios_and_re_derives_the_settlement(): void
    {
        $admin = $this->admin();
        $snapshot = $this->snapshot($admin, ['500.00', '500.00']);
        $ids = $this->participantIds($snapshot);
        $rule = $this->rule($admin);

        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '10000.00', 2026, 5);
        app(ApproveMonthlyProfitAction::class)->execute($admin, $profit);

        // Simulate the historical defect: capital corrected behind the engine's
        // back while the stored ratios were left stale and the header drifted.
        $snapshot->items()->update(['participant_ratio_snapshot' => '0.1000']);
        $snapshot->forceFill(['total_capital' => '999.00'])->saveQuietly();

        $settlement = app(CreateAnnualSettlementAction::class)->execute($admin, 2026);

        $this->artisan('finance:reconcile', ['--year' => 2026, '--force' => true])->assertExitCode(0);

        self::assertSame('1.0000', $this->sum($this->ratios($snapshot->fresh())));
        self::assertSame('1000.00', (string) $snapshot->fresh()->total_capital);

        $item = SettlementItem::query()
            ->where('settlement_id', $settlement->id)
            ->where('participant_id', $ids[1])
            ->firstOrFail();

        self::assertSame('3250.00', (string) $item->net_payable);
        self::assertSame('6500.00', (string) $settlement->fresh()->participant_profit_share);
    }

    public function test_reconcile_is_idempotent_after_a_capital_page_edit(): void
    {
        $admin = $this->admin();
        $snapshot = $this->snapshot($admin, ['6000000.00', '4000000.00']);
        $ids = $this->participantIds($snapshot);
        $rule = $this->rule($admin);

        $profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, '100000.00', 2026, 5);
        app(ApproveMonthlyProfitAction::class)->execute($admin, $profit);
        app(CreateAnnualSettlementAction::class)->execute($admin, 2026);

        $this->withSession(['web_admin_id' => $admin->id])
            ->patchJson("/admin/capital/{$snapshot->id}", [
                'items' => [
                    ['participant_id' => $ids[0], 'capital' => '5000000.00'],
                    ['participant_id' => $ids[1], 'capital' => '5000000.00'],
                ],
            ])
            ->assertOk();

        $this->artisan('finance:reconcile', ['--year' => 2026, '--force' => true])->assertExitCode(0);
        $first = SettlementItem::query()->orderBy('participant_id')->pluck('net_payable', 'participant_id')->all();

        $this->artisan('finance:reconcile', ['--year' => 2026, '--force' => true])->assertExitCode(0);
        $second = SettlementItem::query()->orderBy('participant_id')->pluck('net_payable', 'participant_id')->all();

        self::assertSame($first, $second);
        self::assertSame('32500.00', $first[$ids[0]]);
        self::assertSame('32500.00', $first[$ids[1]]);
    }

    public function test_recalculating_a_stale_snapshot_repairs_ratios_and_the_header_total(): void
    {
        $admin = $this->admin();
        $snapshot = $this->snapshot($admin, ['70000000.00', '0.00']);
        $ids = $this->participantIds($snapshot);

        // Reproduce the historical corruption: correct capitals, stale ratios.
        $snapshot->items()->update(['participant_ratio_snapshot' => '0.0000']);
        $snapshot->forceFill(['total_capital' => '1.00'])->saveQuietly();

        $result = $snapshot->recalculateRatios();

        self::assertTrue($result['changed']);
        self::assertSame('70000000.00', $result['total_capital']);
        self::assertSame('70000000.00', (string) $snapshot->fresh()->total_capital);
        self::assertSame('1.0000', $this->ratios($snapshot)[$ids[0]]);
        self::assertSame('0.0000', $this->ratios($snapshot)[$ids[1]]);
        self::assertSame('1.0000', $this->sum($this->ratios($snapshot)));

        self::assertFalse(
            $snapshot->fresh()->recalculateRatios()['changed'],
            'Repair must be idempotent.',
        );
    }

    public function test_adding_a_partner_to_a_snapshot_is_audited(): void
    {
        $admin = $this->admin();
        $snapshot = $this->snapshot($admin, ['600.00', '400.00']);
        $ids = $this->participantIds($snapshot);
        $newcomer = Participant::factory()->create(['status' => 'active']);

        $this->withSession(['web_admin_id' => $admin->id])
            ->patchJson("/admin/capital/{$snapshot->id}", [
                'items' => [
                    ['participant_id' => $ids[0], 'capital' => '600.00'],
                    ['participant_id' => $ids[1], 'capital' => '400.00'],
                    ['participant_id' => $newcomer->id, 'capital' => '300.00'],
                ],
            ])
            ->assertOk();

        $created = AuditLog::query()
            ->where('action', 'capital_snapshot_item_created')
            ->where('auditable_type', 'capital_snapshot_item')
            ->firstOrFail();

        self::assertSame((int) $snapshot->id, (int) ($created->metadata['snapshot_id'] ?? 0));
        self::assertSame((int) $newcomer->id, (int) ($created->metadata['participant_id'] ?? 0));
        self::assertSame('300.00', (string) ($created->metadata['new_capital'] ?? ''));

        $item = CapitalSnapshotItem::query()
            ->where('capital_snapshot_id', $snapshot->id)
            ->where('participant_id', $newcomer->id)
            ->firstOrFail();

        self::assertSame($item->id, (int) $created->auditable_id);
        self::assertSame('1300.00', (string) $snapshot->fresh()->total_capital);
        self::assertSame('0.2308', (string) $item->participant_ratio_snapshot);
        self::assertSame('1.0000', $this->sum($this->ratios($snapshot)));
    }

    private function admin(): Admin
    {
        return Admin::factory()->create([
            'username' => 'capital-ratio-sync',
            'password' => Hash::make('secret123'),
            'status' => 'active',
            'name' => 'Capital Ratio Sync',
            'role' => 'super-admin',
            'is_super_admin' => true,
        ]);
    }

    private function rule(Admin $admin): DistributionRule
    {
        return DistributionRule::query()->create([
            'effective_from' => '2026-01-01',
            'management_fee_rate' => '0.2500',
            'depreciation_fund_rate' => '0.0500',
            'growth_fund_rate' => '0.0250',
            'incentive_fund_rate' => '0.0250',
            'distributed_share_rate' => '0.6500',
            'status' => 'active',
            'created_by_admin_id' => $admin->id,
        ]);
    }

    /**
     * @param  array<int, string>  $capitals
     */
    private function snapshot(Admin $admin, array $capitals): CapitalSnapshot
    {
        $participants = collect($capitals)
            ->map(static fn (): Participant => Participant::factory()->create([
                'status' => 'active',
                'password' => Hash::make('secret123'),
            ]));

        $total = '0.00';
        foreach ($capitals as $capital) {
            $total = bcadd($total, $capital, 2);
        }

        $snapshot = CapitalSnapshot::query()->create([
            'snapshot_date' => '2026-05-28',
            'year' => 2026,
            'month' => 5,
            'total_capital' => $total,
            'status' => 'final',
            'created_by_admin_id' => $admin->id,
        ]);

        $ratios = app(CapitalCalculatorService::class)->recalculateRatios(
            $participants->mapWithKeys(
                static fn (Participant $participant, int $index): array => [$participant->id => $capitals[$index]],
            )->all(),
        );

        foreach ($participants as $index => $participant) {
            $snapshot->items()->create([
                'participant_id' => $participant->id,
                'participant_capital_snapshot' => $capitals[$index],
                'participant_ratio_snapshot' => $ratios[$participant->id],
            ]);
        }

        foreach (['management_fund', 'growth_fund', 'incentive_fund', 'depreciation_fund'] as $code) {
            Fund::query()->firstOrCreate(
                ['code' => $code],
                ['name' => $code, 'current_balance' => '0.00', 'status' => 'active', 'created_by_admin_id' => $admin->id],
            );
        }

        return $snapshot;
    }

    /** @return array<int, int> */
    private function participantIds(CapitalSnapshot $snapshot): array
    {
        return $snapshot->items()
            ->orderBy('id')
            ->pluck('participant_id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /** @return array<int, int> */
    private function itemIds(CapitalSnapshot $snapshot): array
    {
        return $snapshot->items()->orderBy('id')->pluck('id')->all();
    }

    /** @return array<int, string> */
    private function ratios(CapitalSnapshot $snapshot): array
    {
        return $snapshot->fresh()->items()
            ->orderBy('id')
            ->get()
            ->mapWithKeys(static fn (CapitalSnapshotItem $item): array => [
                (int) $item->participant_id => (string) $item->participant_ratio_snapshot,
            ])
            ->all();
    }

    /**
     * @param  array<int, string>  $values
     */
    private function sum(array $values): string
    {
        return array_reduce(
            $values,
            static fn (string $carry, string $value): string => bcadd($carry, $value, 4),
            '0.0000',
        );
    }
}
