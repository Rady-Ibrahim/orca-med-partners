<?php

declare(strict_types=1);

namespace App\Actions\Settlements;

use App\Domain\Financial\Exceptions\InvalidAnnualSettlementException;
use App\Models\Admin;
use App\Models\Settlement;
use App\Services\SecurityAuditService;
use Illuminate\Support\Facades\DB;

final class CancelSettlementAction
{
    public function __construct(private SecurityAuditService $audit) {}

    public function execute(Admin $admin, Settlement $settlement): Settlement
    {
        return DB::transaction(function () use ($admin, $settlement) {
            $locked = Settlement::query()->lockForUpdate()->findOrFail($settlement->id);
            if ($locked->status !== 'draft') {
                throw new InvalidAnnualSettlementException('Only draft settlements can be cancelled.');
            }

            $locked->status = 'cancelled';
            $locked->save();
            $this->audit->log('settlement_cancelled', $admin, 'settlement', $locked->id, [
                'old_status' => 'draft',
                'new_status' => 'cancelled',
            ]);

            return $locked->fresh('items');
        });
    }
}
