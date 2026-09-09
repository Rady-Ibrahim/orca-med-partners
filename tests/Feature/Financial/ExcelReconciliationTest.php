<?php

declare(strict_types=1);

namespace Tests\Feature\Financial;

use App\Domain\Financial\Services\FinancialCalculationService;
use App\Domain\Financial\ValueObjects\FinancialRoundingService;
use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\DistributionRule;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExcelReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_monthly_profit_series_reconciles_to_the_participant_pool(): void
    {
        $admin = Admin::factory()->create();
        $participant = Participant::factory()->create();
        $rule = DistributionRule::query()->create([
            'effective_from' => '2026-01-01',
            'management_fee_rate' => '0.2500',
            'depreciation_fund_rate' => '0.0500',
            'growth_fund_rate' => '0.0250',
            'incentive_fund_rate' => '0.0250',
            'distributed_share_rate' => '0.6500',
            'status' => 'active',
            'created_by_admin_id' => $admin->id,
        ]);
        $grossProfits = ['2000000.00', '1500000.00', '2000000.00', '2500000.00', '2000000.00', '3000000.00', '2200000.00', '2500000.00', '2000000.00', '2000000.00', '2500000.00', '2200000.00'];
        $totalGross = '0.00';
        $totalDistributed = '0.00';

        foreach ($grossProfits as $index => $grossProfit) {
            $totalGross = bcadd($totalGross, $grossProfit, 2);
            $snapshot = CapitalSnapshot::query()->create([
                'snapshot_date' => sprintf('2026-%02d-01', $index + 1),
                'year' => 2026,
                'month' => $index + 1,
                'total_capital' => '10000000.00',
                'status' => 'final',
                'created_by_admin_id' => $admin->id,
            ]);
            $snapshot->items()->create([
                'participant_id' => $participant->id,
                'participant_capital_snapshot' => '10000000.00',
                'participant_ratio_snapshot' => '1.0000',
            ]);

            $result = app(FinancialCalculationService::class)->calculate($grossProfit, $rule, $snapshot);
            $totalDistributed = bcadd($totalDistributed, $result->distributedPool, 2);
        }

        self::assertSame('26400000.00', $totalGross);
        self::assertSame('17160000.00', $totalDistributed);
    }
}
