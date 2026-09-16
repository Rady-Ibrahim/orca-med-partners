<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Financial\Exceptions\ImmutableFinancialRecordException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipantFundAllocation extends Model
{
    protected $table = 'participant_fund_allocations';

    protected $fillable = [
        'fund_id',
        'monthly_profit_id',
        'participant_id',
        'amount',
        'allocation_type',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $allocation): void {
            if ($allocation->monthly_profit_id !== null
                && $allocation->monthlyProfit?->status === 'approved') {
                throw new ImmutableFinancialRecordException('Fund allocations linked to an approved profit period cannot be deleted.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function monthlyProfit(): BelongsTo
    {
        return $this->belongsTo(MonthlyProfit::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
