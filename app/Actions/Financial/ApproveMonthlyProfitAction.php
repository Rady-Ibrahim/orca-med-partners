<?php

declare(strict_types=1);

namespace App\Actions\Financial;

use App\Domain\Financial\Exceptions\ImmutableFinancialRecordException;
use App\Models\Admin;
use App\Models\MonthlyProfit;
use App\Services\ParticipantNotificationService;
use App\Services\SecurityAuditService;
use Illuminate\Support\Facades\DB;

final class ApproveMonthlyProfitAction
{
    public function __construct(private SecurityAuditService $audit, private ParticipantNotificationService $notifications) {}

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
}
