<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Financial\Exceptions\ImmutableFinancialRecordException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipantProfitAllocation extends Model
{
    protected $table = 'participant_profit_allocations';

    protected $fillable = [
        'monthly_profit_id',
        'participant_id',
        'amount',
        'share_ratio',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'share_ratio' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $allocation) {
            if ($allocation->monthlyProfit()->where('status', 'approved')->exists()) {
                throw new ImmutableFinancialRecordException('Allocations for approved monthly profit records are immutable.');
            }
        });

        static::deleting(function (self $allocation) {
            if ($allocation->monthlyProfit()->where('status', 'approved')->exists()) {
                throw new ImmutableFinancialRecordException('Allocations for approved monthly profit records cannot be deleted.');
            }
        });
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
