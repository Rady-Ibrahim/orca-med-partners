<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Financial\Exceptions\ImmutableFinancialRecordException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlyProfit extends Model
{
    protected $table = 'monthly_profits';

    protected $fillable = [
        'capital_snapshot_id',
        'distribution_rule_id',
        'parent_id',
        'year',
        'month',
        'version',
        'status',
        'gross_profit',
        'management_amount',
        'depreciation_amount',
        'growth_amount',
        'incentive_amount',
        'distributed_amount',
        'rounding_delta_adjustment',
        'created_by_admin_id',
        'approved_by_admin_id',
        'approved_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'gross_profit' => 'decimal:2',
            'management_amount' => 'decimal:2',
            'depreciation_amount' => 'decimal:2',
            'growth_amount' => 'decimal:2',
            'incentive_amount' => 'decimal:2',
            'distributed_amount' => 'decimal:2',
            'rounding_delta_adjustment' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $profit) {
            if ($profit->getOriginal('status') === 'approved' || $profit->status === 'approved') {
                throw new ImmutableFinancialRecordException('Approved monthly profit records are immutable and cannot be updated.');
            }

            if ($profit->getOriginal('status') === 'approved' && $profit->status !== 'approved') {
                throw new ImmutableFinancialRecordException('Approved monthly profit records cannot return to draft or any other status.');
            }
        });

        static::deleting(function (self $profit) {
            if ($profit->status === 'approved') {
                throw new ImmutableFinancialRecordException('Approved monthly profit records cannot be deleted.');
            }
        });
    }

    public function capitalSnapshot(): BelongsTo
    {
        return $this->belongsTo(CapitalSnapshot::class);
    }

    public function distributionRule(): BelongsTo
    {
        return $this->belongsTo(DistributionRule::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ParticipantProfitAllocation::class);
    }
}
