<?php

declare(strict_types=1);

namespace App\Actions\Settlements;

use App\Domain\Financial\Exceptions\InvalidAnnualSettlementException;
use App\Domain\Financial\ValueObjects\FinancialRoundingService;
use App\Models\Admin;
use App\Models\MonthlyProfit;
use App\Models\Settlement;
use App\Models\SettlementItem;
use App\Models\ParticipantFundAllocation;
use App\Services\SecurityAuditService;
use App\Domain\Financial\Services\SettlementAmountDueService;
use Illuminate\Support\Facades\DB;

final class CreateAnnualSettlementAction
{
    public function __construct(
        private FinancialRoundingService $rounding,
        private SettlementAmountDueService $amountDue,
        private SecurityAuditService $audit,
    ) {}

    public function execute(Admin $admin, int $year, ?int $parentId = null, string|int $previousPayments = '0.00'): Settlement
    {
        return DB::transaction(function () use ($admin, $year, $parentId, $previousPayments) {
            if ($parentId === null && Settlement::query()->where('year', $year)->whereIn('status', ['draft', 'approved', 'partially_paid', 'paid'])->exists()) {
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

            $fundByParticipant = ParticipantFundAllocation::query()
                ->join('monthly_profits', 'monthly_profits.id', '=', 'participant_fund_allocations.monthly_profit_id')
                ->where('monthly_profits.year', $year)
                ->where('monthly_profits.status', 'approved')
                ->select('participant_fund_allocations.participant_id', DB::raw('SUM(participant_fund_allocations.amount) as amount'))
                ->groupBy('participant_fund_allocations.participant_id')
                ->pluck('amount', 'participant_id');

            $profitTotal = '0.00';
            foreach ($profitByParticipant as $amount) {
                $profitTotal = bcadd($profitTotal, $amount, 2);
            }

            $fundTotal = '0.00';
            foreach ($fundByParticipant as $amount) {
                $fundTotal = bcadd($fundTotal, (string) $amount, 2);
            }
            $totalDue = $this->amountDue->calculate($profitTotal, $fundTotal, $previousPayments)['amount_due'];

            $settlement = Settlement::query()->create([
                'parent_id' => $parentId,
                'year' => $year,
                'version' => (int) Settlement::query()->where('year', $year)->max('version') + 1,
                'status' => 'draft',
                'total_distributed_amount' => $profitTotal,
                'participant_profit_share' => $profitTotal,
                'participant_fund_share' => $fundTotal,
                'net_payable' => $totalDue,
                'amount_due' => $totalDue,
                'paid_amount' => '0.00',
                'created_by_admin_id' => $admin->id,
                'notes' => $parentId
                    ? sprintf('Revision settlement. Amount due (%s) = approved annual participant profit + approved fund allocations − previous payments already recorded on the parent settlement.', $totalDue)
                    : 'Amount due equals approved annual participant profit plus approved participant fund allocations. Principal remains excluded. Previous payments are recorded separately in settlement_payments.',
            ]);

            foreach ($profitByParticipant as $participantId => $amount) {
                SettlementItem::query()->create([
                    'settlement_id' => $settlement->id,
                    'participant_id' => $participantId,
                    'profit_share' => $amount,
                    'fund_share' => (string) ($fundByParticipant[$participantId] ?? '0.00'),
                    'net_payable' => bcadd($amount, (string) ($fundByParticipant[$participantId] ?? '0.00'), 2),
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
