<?php

declare(strict_types=1);

namespace App\Actions\Settlements;

use App\Domain\Financial\Exceptions\InvalidAnnualSettlementException;
use App\Models\Admin;
use App\Models\Settlement;
use App\Services\SecurityAuditService;
use Illuminate\Support\Facades\DB;

final class CreateSettlementRevisionAction
{
    public function __construct(
        private CreateAnnualSettlementAction $create,
        private SecurityAuditService $audit,
    ) {}

    public function execute(Admin $admin, Settlement $settlement): Settlement
    {
        return DB::transaction(function () use ($admin, $settlement) {
            $locked = Settlement::query()->lockForUpdate()->findOrFail($settlement->id);
            if (! in_array($locked->status, ['approved', 'paid'], true)) {
                throw new InvalidAnnualSettlementException('Only approved or paid settlements can be revised.');
            }

            $revision = $this->create->execute($admin, (int) $locked->year, $locked->id);
            if ($locked->status === 'approved') {
                $locked->status = 'superseded';
                $locked->save();
            }

            $this->audit->log('settlement_revised', $admin, 'settlement', $revision->id, [
                'parent_id' => $locked->id,
                'parent_status' => $locked->status,
                'version' => $revision->version,
            ]);

            return $revision;
        });
    }
}
