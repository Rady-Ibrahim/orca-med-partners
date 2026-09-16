<?php

declare(strict_types=1);

namespace App\Actions\Investment;

use App\Domain\Financial\Exceptions\ImmutableFinancialRecordException;
use App\Models\Admin;
use App\Models\Investment;
use App\Services\SecurityAuditService;
use Illuminate\Support\Facades\DB;

final class ApproveInvestmentAction
{
    public function __construct(private SecurityAuditService $audit)
    {
    }

    public function execute(Admin $admin, Investment $investment): Investment
    {
        return DB::transaction(function () use ($admin, $investment) {
            $investment = Investment::query()->lockForUpdate()->findOrFail($investment->id);

            if ($investment->status !== 'pending' || $investment->approved_at !== null) {
                throw new ImmutableFinancialRecordException('Only pending investments can be approved.');
            }

            $investment->forceFill([
                'status' => 'approved',
                'approved_by_admin_id' => $admin->id,
                'approved_at' => now(),
            ])->save();

            $this->audit->log('investment_approved', $admin, 'investment', $investment->id, [
                'investor' => $investment->participant_id,
                'amount' => (string) $investment->amount,
                'approved_at' => $investment->approved_at?->toDateTimeString(),
            ]);

            return $investment->fresh('participant');
        });
    }
}
