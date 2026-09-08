<?php

declare(strict_types=1);

namespace App\Actions\Settlements;

use App\Domain\Financial\Exceptions\InvalidAnnualSettlementException;
use App\Models\Admin;
use App\Models\Settlement;
use App\Services\ParticipantNotificationService;
use App\Services\SecurityAuditService;
use Illuminate\Support\Facades\DB;

final class ApproveSettlementAction
{
    public function __construct(private SecurityAuditService $audit, private ParticipantNotificationService $notifications) {}

    public function execute(Admin $admin, Settlement $settlement): Settlement
    {
        return DB::transaction(function () use ($admin, $settlement) {
            $locked = Settlement::query()->lockForUpdate()->findOrFail($settlement->id);
            if ($locked->status !== 'draft' || ! $locked->items()->exists()) {
                throw new InvalidAnnualSettlementException('Only complete draft settlements can be approved.');
            }

            $itemTotal = (string) $locked->items()->sum('net_payable');
            if (bccomp($itemTotal, (string) $locked->amount_due, 2) !== 0) {
                throw new InvalidAnnualSettlementException('Settlement items do not reconcile to amount due.');
            }

            $locked->forceFill([
                'status' => 'approved',
                'approved_by_admin_id' => $admin->id,
                'approved_at' => now(),
            ])->saveQuietly();

            $this->audit->log('settlement_approved', $admin, 'settlement', $locked->id, [
                'old_status' => 'draft',
                'new_status' => 'approved',
            ]);

            $approved = $locked->fresh('items.participant');
            $this->notifications->afterCommit(function () use ($approved, $admin): void {
                foreach ($approved->items as $item) {
                    if ($item->participant) {
                        $this->notifications->createForParticipant($item->participant, 'settlement_approval', 'اعتماد التسوية السنوية', 'تم اعتماد التسوية السنوية الخاصة بك.', $admin, ['settlement_id' => $approved->id]);
                    }
                }
            });

            return $approved;
        });
    }
}
