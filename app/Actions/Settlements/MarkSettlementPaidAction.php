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
    public function __construct(private RecordSettlementPaymentAction $recordPayment) {}

    public function execute(Admin $admin, Settlement $settlement): Settlement
    {
        $locked = Settlement::query()->findOrFail($settlement->id);
        if ($locked->status !== 'approved') {
            throw new InvalidAnnualSettlementException('Only approved settlements can be paid.');
        }

        $remaining = bcsub((string) $locked->amount_due, (string) $locked->paid_amount, 2);

        return $this->recordPayment->execute($admin, $locked, ['amount' => $remaining, 'payment_source' => 'other']);
    }
}
