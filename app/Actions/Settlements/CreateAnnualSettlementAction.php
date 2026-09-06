<?php

declare(strict_types=1);

namespace App\Actions\Settlements;

use App\Domain\Financial\Exceptions\InvalidAnnualSettlementException;
use App\Domain\Financial\ValueObjects\FinancialRoundingService;
use App\Models\Admin;
use App\Models\MonthlyProfit;
use App\Models\Settlement;
use App\Models\SettlementItem;
use App\Services\SecurityAuditService;
use Illuminate\Support\Facades\DB;

final class CreateAnnualSettlementAction
{
    public function __construct(
        private FinancialRoundingService $rounding,
        private SecurityAuditService $audit,
    ) {}

    public function execute(Admin $admin, int $year, ?int $parentId = null): Settlement
    {
        return DB::transaction(function () use ($admin, $year, $parentId) {
            if ($parentId === null && Settlement::query()->where('year', $year)->whereIn('status', ['draft', 'approved', 'paid'])->exists()) {
                throw new InvalidAnnualSettlementException('An active settlement already exists for this year.');
            }

            $allocations = DB::table('participant_profit_allocations as allocations')
                ->join('monthly_profits as profits', 'profits.id', '=', 'allocations.monthly_profit_id')
                ->where('profits.year', $year)
                ->where('profits.status', 'approved')
                ->select('allocations.participant_id', 'allocations.amount')
                ->get();

            if ($allocations->isEmpty()) {
                throw new InvalidAnnualSettlementException('No approved monthly profit allocations exist for this year.');
            }

            $profitByParticipant = [];
            foreach ($allocations as $allocation) {
                $participantId = (int) $allocation->participant_id;
                $profitByParticipant[$participantId] = $this->rounding->money(bcadd(
                    $profitByParticipant[$participantId] ?? '0.00',
                    (string) $allocation->amount,
                    2,
                ));
            }

            $profitTotal = '0.00';
            foreach ($profitByParticipant as $amount) {
                $profitTotal = bcadd($profitTotal, $amount, 2);
            }

            $settlement = Settlement::query()->create([
                'parent_id' => $parentId,
                'year' => $year,
                'version' => (int) Settlement::query()->where('year', $year)->max('version') + 1,
                'status' => 'draft',
                'total_distributed_amount' => $profitTotal,
                'participant_profit_share' => $profitTotal,
                'participant_fund_share' => '0.00',
                'net_payable' => $profitTotal,
                'amount_due' => $profitTotal,
                'paid_amount' => '0.00',
                'created_by_admin_id' => $admin->id,
                'notes' => 'Amount due equals approved annual participant profit. No prior payout/deduction source exists in the current domain, so deductions are 0.00. Participant fund share and principal are excluded by policy. Partial payment and paid-settlement reversal remain [NEEDS BUSINESS DECISION].',
            ]);

            foreach ($profitByParticipant as $participantId => $amount) {
                SettlementItem::query()->create([
                    'settlement_id' => $settlement->id,
                    'participant_id' => $participantId,
                    'profit_share' => $amount,
                    'fund_share' => '0.00',
                    'net_payable' => $amount,
                    'payment_status' => 'pending',
                    'paid_amount' => '0.00',
                ]);
            }

            $this->audit->log('settlement_created', $admin, 'settlement', $settlement->id, [
                'year' => $year,
                'version' => $settlement->version,
                'participant_profit_share' => $profitTotal,
            ]);

            return $settlement->load('items');
        });
    }
}
