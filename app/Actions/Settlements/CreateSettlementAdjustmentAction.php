<?php

declare(strict_types=1);

namespace App\Actions\Settlements;

use App\Domain\Financial\Exceptions\InvalidAnnualSettlementException;
use App\Models\Admin;
use App\Models\Settlement;
use App\Models\SettlementAdjustment;
use App\Services\SecurityAuditService;
use Illuminate\Support\Facades\DB;

final class CreateSettlementAdjustmentAction
{
    public function __construct(private SecurityAuditService $audit) {}

    public function execute(Admin $admin, Settlement $settlement, array $attributes): SettlementAdjustment
    {
        return DB::transaction(function () use ($admin, $settlement, $attributes): SettlementAdjustment {
            $locked = Settlement::query()->lockForUpdate()->findOrFail($settlement->id);
            if ($locked->status !== 'paid') {
                throw new InvalidAnnualSettlementException('Adjustments are only available for paid settlements.');
            }

            if ($attributes['settlement_payment_id'] ?? null) {
                $paymentBelongsToSettlement = $locked->payments()->whereKey($attributes['settlement_payment_id'])->exists();
                if (! $paymentBelongsToSettlement) {
                    throw new InvalidAnnualSettlementException('The payment does not belong to this settlement.');
                }
            }

            $adjustment = SettlementAdjustment::query()->create([
                'settlement_id' => $locked->id,
                'settlement_payment_id' => $attributes['settlement_payment_id'] ?? null,
                'type' => $attributes['type'],
                'direction' => $attributes['direction'],
                'amount' => $attributes['amount'],
                'reason' => $attributes['reason'],
                'reference' => $attributes['reference'] ?? null,
                'created_by_admin_id' => $admin->id,
            ]);

            $this->audit->log('settlement_adjustment_created', $admin, 'settlement_adjustment', $adjustment->id, [
                'settlement_id' => $locked->id,
                'settlement_payment_id' => $adjustment->settlement_payment_id,
                'type' => $adjustment->type,
                'direction' => $adjustment->direction,
                'amount' => (string) $adjustment->amount,
                'reason' => $adjustment->reason,
            ]);

            return $adjustment->fresh();
        });
    }
}
