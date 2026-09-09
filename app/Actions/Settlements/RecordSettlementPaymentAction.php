<?php

declare(strict_types=1);

namespace App\Actions\Settlements;

use App\Domain\Financial\Exceptions\InvalidAnnualSettlementException;
use App\Models\Admin;
use App\Models\Settlement;
use App\Models\SettlementPayment;
use App\Services\SecurityAuditService;
use App\Domain\Financial\Services\SettlementAmountDueService;
use Illuminate\Support\Facades\DB;

final class RecordSettlementPaymentAction
{
    public function __construct(private SecurityAuditService $audit, private SettlementAmountDueService $amountDue) {}

    public function execute(Admin $admin, Settlement $settlement, array $attributes): Settlement
    {
        return DB::transaction(function () use ($admin, $settlement, $attributes): Settlement {
            $locked = Settlement::query()->lockForUpdate()->findOrFail($settlement->id);
            if (! in_array($locked->status, ['approved', 'partially_paid'], true)) {
                throw new InvalidAnnualSettlementException('Only approved or partially paid settlements can receive payments.');
            }

            $amount = (string) $attributes['amount'];
            $paid = (string) $locked->payments()->sum('amount');
            $calculation = $this->amountDue->calculate($locked->participant_profit_share, $locked->participant_fund_share, '0.00');
            $newPaid = bcadd($paid, $amount, 2);
            if (bccomp($newPaid, $calculation['amount_due'], 2) > 0) {
                throw new InvalidAnnualSettlementException('Payment amount exceeds settlement amount due.');
            }

            $payment = SettlementPayment::query()->create([
                'settlement_id' => $locked->id,
                'amount' => $amount,
                'paid_at' => $attributes['paid_at'] ?? now(),
                'payment_method' => $attributes['payment_method'] ?? null,
                'payment_source' => $attributes['payment_source'] ?? 'other',
                'reference' => $attributes['reference'] ?? null,
                'description' => $attributes['description'] ?? null,
                'created_by_admin_id' => $admin->id,
            ]);

            $status = bccomp($newPaid, (string) $locked->amount_due, 2) === 0 ? 'paid' : 'partially_paid';
            $locked->forceFill([
                'status' => $status,
                'paid_by_admin_id' => $admin->id,
                'payout_at' => $payment->paid_at,
                'paid_amount' => $newPaid,
            ])->saveQuietly();

            if ($status === 'paid') {
                $locked->items()->update([
                    'payment_status' => 'paid',
                    'paid_amount' => DB::raw('net_payable'),
                    'paid_at' => $payment->paid_at,
                ]);
            }

            $this->audit->log('settlement_payment_recorded', $admin, 'settlement_payment', $payment->id, [
                'settlement_id' => $locked->id,
                'amount' => $amount,
                'new_paid_amount' => $newPaid,
                'new_status' => $status,
                'payment_source' => $payment->payment_source,
            ]);

            if ($status === 'paid') {
                $this->audit->log('settlement_paid', $admin, 'settlement', $locked->id, [
                    'old_status' => 'approved_or_partially_paid',
                    'new_status' => 'paid',
                    'paid_amount' => $newPaid,
                ]);
            }

            return $locked->fresh(['items', 'payments']);
        });
    }
}
