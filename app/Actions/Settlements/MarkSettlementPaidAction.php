<?php

declare(strict_types=1);

namespace App\Actions\Settlements;

use App\Domain\Financial\Exceptions\InvalidAnnualSettlementException;
use App\Models\Admin;
use App\Models\Settlement;
use App\Services\SecurityAuditService;
use Illuminate\Support\Facades\DB;

final class MarkSettlementPaidAction
{
    public function __construct(private SecurityAuditService $audit) {}

    public function execute(Admin $admin, Settlement $settlement): Settlement
    {
        return DB::transaction(function () use ($admin, $settlement) {
            $locked = Settlement::query()->lockForUpdate()->findOrFail($settlement->id);
            if ($locked->status !== 'approved') {
                throw new InvalidAnnualSettlementException('Only approved settlements can be paid.');
            }

            $locked->forceFill([
                'status' => 'paid',
                'paid_by_admin_id' => $admin->id,
                'payout_at' => now(),
                'paid_amount' => $locked->amount_due,
            ])->saveQuietly();

            $locked->items()->update([
                'payment_status' => 'paid',
                'paid_amount' => DB::raw('net_payable'),
                'paid_at' => now(),
            ]);

            $this->audit->log('settlement_paid', $admin, 'settlement', $locked->id, [
                'old_status' => 'approved',
                'new_status' => 'paid',
                'paid_amount' => $locked->paid_amount,
            ]);

            return $locked->fresh('items');
        });
    }
}
