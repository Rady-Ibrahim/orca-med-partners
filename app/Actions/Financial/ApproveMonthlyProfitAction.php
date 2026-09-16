<?php

declare(strict_types=1);

namespace App\Actions\Financial;

use App\Domain\Financial\Exceptions\ImmutableFinancialRecordException;
use App\Domain\Financial\Services\FundBalanceService;
use App\Enums\FundTransactionType;
use App\Models\Admin;
use App\Models\Fund;
use App\Models\MonthlyProfit;
use App\Services\ParticipantNotificationService;
use App\Services\SecurityAuditService;
use Illuminate\Support\Facades\DB;

final class ApproveMonthlyProfitAction
{
    public function __construct(
        private SecurityAuditService $audit,
        private ParticipantNotificationService $notifications,
        private FundBalanceService $funds,
    ) {}

    public function execute(Admin $admin, MonthlyProfit $profit): MonthlyProfit
    {
        return DB::transaction(function () use ($admin, $profit) {
            $profit = MonthlyProfit::query()->lockForUpdate()->findOrFail($profit->id);
            if ($profit->status !== 'draft') {
                throw new ImmutableFinancialRecordException('Only draft monthly profit records can be approved.');
            }

            $profit->forceFill([
                'status' => 'approved',
                'approved_by_admin_id' => $admin->id,
                'approved_at' => now(),
            ])->saveQuietly();

            $this->depositFundShares($admin, $profit);

            $this->audit->log('monthly_profit_approved', $admin, 'monthly_profit', $profit->id, [
                'version' => $profit->version,
            ]);

            $approved = $profit->fresh('allocations');
            $this->notifications->afterCommit(function () use ($approved, $admin): void {
                foreach ($approved->allocations as $allocation) {
                    $participant = $allocation->participant;
                    if ($participant) {
                        $this->notifications->createForParticipant($participant, 'profit_update', 'اعتماد أرباح شهرية', 'تم اعتماد أرباح شهرية جديدة لحسابك.', $admin, ['monthly_profit_id' => $approved->id]);
                    }
                }
            });

            return $approved;
        });
    }

    private function depositFundShares(Admin $admin, MonthlyProfit $profit): void
    {
        if ($profit->version !== 1 || $profit->parent_id !== null) {
            return;
        }

        $entries = [
            'growth_fund' => (string) $profit->growth_amount,
            'incentive_fund' => (string) $profit->incentive_amount,
            'depreciation_fund' => (string) $profit->depreciation_amount,
        ];

        foreach ($entries as $code => $amount) {
            $fund = Fund::query()->where('code', $code)->first();

            if (! $fund || bccomp($amount, '0', 2) <= 0) {
                continue;
            }

            $this->funds->applyTransaction(
                fund: $fund,
                amount: $amount,
                transactionType: FundTransactionType::DEPOSIT,
                monthlyProfitId: $profit->id,
                reference: "PROFIT-{$profit->id}",
                createdByAdminId: $admin->id,
                transactionDate: $profit->approved_at,
                description: "تحويل من أرباح شهر {$profit->month}/{$profit->year}",
                actor: $admin,
            );
        }
    }
}
