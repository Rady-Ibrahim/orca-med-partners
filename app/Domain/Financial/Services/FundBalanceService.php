<?php

declare(strict_types=1);

namespace App\Domain\Financial\Services;

use App\Enums\FundTransactionType;
use App\Models\Fund;
use App\Models\FundTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class FundBalanceService
{
    public function applyTransaction(Fund $fund, float|int|string $amount, string $transactionType, ?int $monthlyProfitId = null, ?string $reference = null, ?string $notes = null, ?int $createdByAdminId = null): FundTransaction
    {
        $normalizedAmount = $this->normalizeAmount($amount);

        $type = FundTransactionType::tryFrom($transactionType);
        if ($type === null) {
            throw new RuntimeException('Unsupported fund transaction type.');
        }

        return DB::transaction(function () use ($fund, $normalizedAmount, $type, $monthlyProfitId, $reference, $notes, $createdByAdminId) {
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
                'amount' => $normalizedAmount,
                'resulting_balance' => $newBalance,
                'reference' => $reference,
                'notes' => $notes,
                'created_by_admin_id' => $createdByAdminId,
            ]);

            $lockedFund->current_balance = $newBalance;
            $lockedFund->save();

            return $transaction->fresh();
        });
    }

    private function normalizeAmount(float|int|string $amount): string
    {
        $normalized = is_string($amount) ? $amount : (string) $amount;

        if (! preg_match('/^\d+(\.\d+)?$/', $normalized)) {
            throw new RuntimeException('Fund transaction amount must be a non-negative decimal value.');
        }

        return $normalized;
    }
}
