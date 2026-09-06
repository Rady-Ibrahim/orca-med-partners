<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DistributionRule extends Model
{
    protected $table = 'distribution_rules';

    protected $fillable = [
        'effective_from',
        'effective_to',
        'management_fee_rate',
        'depreciation_fund_rate',
        'growth_fund_rate',
        'incentive_fund_rate',
        'distributed_share_rate',
        'status',
        'is_default',
        'notes',
        'created_by_admin_id',
        'approved_by_admin_id',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'management_fee_rate' => 'decimal:4',
            'depreciation_fund_rate' => 'decimal:4',
            'growth_fund_rate' => 'decimal:4',
            'incentive_fund_rate' => 'decimal:4',
            'distributed_share_rate' => 'decimal:4',
            'approved_at' => 'datetime',
            'is_default' => 'boolean',
        ];
    }

    public function monthlyProfits(): HasMany
    {
        return $this->hasMany(MonthlyProfit::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by_admin_id');
    }
}
