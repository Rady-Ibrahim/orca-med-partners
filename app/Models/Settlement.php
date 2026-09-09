<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Financial\Exceptions\ImmutableFinancialRecordException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Settlement extends Model
{
    protected $table = 'settlements';

    protected $fillable = [
        'parent_id',
        'year',
        'version',
        'status',
        'total_distributed_amount',
        'participant_profit_share',
        'participant_fund_share',
        'net_payable',
        'amount_due',
        'paid_amount',
        'created_by_admin_id',
        'approved_by_admin_id',
        'paid_by_admin_id',
        'approved_at',
        'payout_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_distributed_amount' => 'decimal:2',
            'participant_profit_share' => 'decimal:2',
            'participant_fund_share' => 'decimal:2',
            'net_payable' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'payout_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $settlement) {
            $dirty = array_keys($settlement->getDirty());
            $statusOnly = count(array_diff($dirty, ['status', 'updated_at'])) === 0;
            $originalStatus = $settlement->getOriginal('status');
            $isAllowedCancellation = $originalStatus === 'draft' && $settlement->status === 'cancelled' && $statusOnly;
            $isAllowedSupersession = $originalStatus === 'approved' && $settlement->status === 'superseded' && $statusOnly;

            if (($originalStatus !== 'draft' && ! $isAllowedSupersession) || ($settlement->status === 'cancelled' && ! $isAllowedCancellation)) {
                throw new ImmutableFinancialRecordException('Approved, paid, or cancelled settlement records are immutable.');
            }
        });

        static::deleting(function (self $settlement) {
            if (in_array($settlement->status, ['approved', 'paid', 'cancelled'], true)) {
                throw new ImmutableFinancialRecordException('Approved, paid, or cancelled settlement records cannot be deleted.');
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }

    public function participantItems(int $participantId): HasMany
    {
        return $this->items()->where('participant_id', $participantId);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SettlementPayment::class)->orderBy('paid_at')->orderBy('id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(SettlementAdjustment::class)->latest('id');
    }
}
