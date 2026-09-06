<?php

declare(strict_types=1);

namespace App\Domain\Financial\Services;

use App\Domain\Financial\Exceptions\FundBalanceDriftException;
use App\Enums\FundTransactionType;
use App\Models\Admin;
use App\Models\Fund;
use App\Models\FundTransaction;
use App\Services\SecurityAuditService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FundBalanceService
{
    public function __construct(private SecurityAuditService $audit) {}

    public function applyTransaction(
        Fund $fund,
        int|string $amount,
        FundTransactionType|string $transactionType,
        ?int $monthlyProfitId = null,
        ?string $reference = null,
        ?string $notes = null,
        ?int $createdByAdminId = null,
        ?CarbonInterface $transactionDate = null,
        ?string $description = null,
        ?Admin $actor = null,
    ): FundTransaction {
        $normalizedAmount = $this->normalizeAmount($amount);

        $type = $transactionType instanceof FundTransactionType ? $transactionType : FundTransactionType::tryFrom($transactionType);
        if ($type === null) {
            throw new RuntimeException('Unsupported fund transaction type.');
        }

        return DB::transaction(function () use ($fund, $normalizedAmount, $type, $monthlyProfitId, $reference, $notes, $createdByAdminId, $transactionDate, $description, $actor) {
            $lockedFund = Fund::query()
                ->whereKey($fund->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $currentBalance = (string) $lockedFund->current_balance;
            $newBalance = match ($type) {
                FundTransactionType::DEPOSIT => bcadd($currentBalance, $normalizedAmount, 2),
                FundTransactionType::WITHDRAWAL => bcsub($currentBalance, $normalizedAmount, 2),
                FundTransactionType::ADJUSTMENT => bcadd($currentBalance, $normalizedAmount, 2),
            };

            $transaction = FundTransaction::query()->create([
                'fund_id' => $lockedFund->id,
                'monthly_profit_id' => $monthlyProfitId,
                'transaction_type' => $type->value,
                'transaction_date' => ($transactionDate ?? now())->toDateString(),
                'amount' => $normalizedAmount,
                'resulting_balance' => $newBalance,
                'reference' => $reference,
                'description' => $description,
                'notes' => $notes,
                'created_by_admin_id' => $createdByAdminId,
            ]);

            $lockedFund->current_balance = $newBalance;
            $lockedFund->save();

            $this->audit->log('fund_transaction_created', $actor, 'fund_transaction', $transaction->id, [
                'fund_id' => $lockedFund->id,
                'transaction_type' => $type->value,
                'amount' => $normalizedAmount,
                'old_balance' => $currentBalance,
                'new_balance' => $newBalance,
            ]);

            $this->audit->log(match ($type) {
                FundTransactionType::DEPOSIT => 'fund_deposit',
                FundTransactionType::WITHDRAWAL => 'fund_withdrawal',
                FundTransactionType::ADJUSTMENT => 'fund_adjustment',
            }, $actor, 'fund', $lockedFund->id, [
                'old_balance' => $currentBalance,
                'new_balance' => $newBalance,
            ]);

            $this->audit->log('fund_balance_changed', $actor, 'fund', $lockedFund->id, [
                'old_balance' => $currentBalance,
                'new_balance' => $newBalance,
                'transaction_id' => $transaction->id,
            ]);

            return $transaction->fresh();
        });
    }

    private function normalizeAmount(int|string $amount): string
    {
        $normalized = is_string($amount) ? $amount : (string) $amount;

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized) || bccomp($normalized, '0', 2) <= 0) {
            throw new RuntimeException('Fund transaction amount must be a non-negative decimal value.');
        }

        return bcadd($normalized, '0', 2);
    }

    /** @return array{expected_balance:string, stored_balance:string, is_consistent:bool} */
    public function reconcile(Fund $fund): array
    {
        $expected = '0.00';
        foreach ($fund->transactions()->get(['transaction_type', 'amount']) as $transaction) {
            $expected = match ($transaction->transaction_type) {
                FundTransactionType::DEPOSIT->value => bcadd($expected, (string) $transaction->amount, 2),
                FundTransactionType::WITHDRAWAL->value => bcsub($expected, (string) $transaction->amount, 2),
                FundTransactionType::ADJUSTMENT->value => bcadd($expected, (string) $transaction->amount, 2),
                default => throw new RuntimeException('Unsupported persisted fund transaction type.'),
            };
        }

        $stored = bcadd((string) $fund->current_balance, '0', 2);
        $consistent = bccomp($expected, $stored, 2) === 0;

        return [
            'expected_balance' => $expected,
            'stored_balance' => $stored,
            'is_consistent' => $consistent,
        ];
    }

    public function assertReconciled(Fund $fund): void
    {
        $result = $this->reconcile($fund);
        if (! $result['is_consistent']) {
            throw new FundBalanceDriftException(sprintf(
                'Fund balance drift detected. Expected [%s], stored [%s].',
                $result['expected_balance'],
                $result['stored_balance'],
            ));
        }
    }
}
