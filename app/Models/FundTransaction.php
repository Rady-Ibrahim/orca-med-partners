<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Financial\Exceptions\ImmutableFinancialRecordException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundTransaction extends Model
{
    protected $table = 'fund_transactions';

    protected $fillable = [
        'fund_id',
        'monthly_profit_id',
        'transaction_type',
        'transaction_date',
        'amount',
        'resulting_balance',
        'reference',
        'description',
        'notes',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'resulting_balance' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new ImmutableFinancialRecordException('Fund transactions are immutable; create a new adjustment instead.');
        });

        static::deleting(function () {
            throw new ImmutableFinancialRecordException('Fund transactions cannot be deleted.');
        });
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function monthlyProfit(): BelongsTo
    {
        return $this->belongsTo(MonthlyProfit::class);
    }
}
