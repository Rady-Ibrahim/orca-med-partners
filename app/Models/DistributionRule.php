<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Financial\Rules\DistributionRuleValidator;
use App\Domain\Financial\Services\DistributionRuleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

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

    protected static function booted(): void
    {
        static::saving(function (self $rule) {
            if ($rule->status !== 'active') {
                return;
            }

            DistributionRuleValidator::validate([
                'management_fee_rate' => (string) $rule->management_fee_rate,
                'depreciation_fund_rate' => (string) $rule->depreciation_fund_rate,
                'growth_fund_rate' => (string) $rule->growth_fund_rate,
                'incentive_fund_rate' => (string) $rule->incentive_fund_rate,
                'distributed_share_rate' => (string) $rule->distributed_share_rate,
            ]);

            app(DistributionRuleService::class)->validateEffectiveRange(
                $rule->effective_from?->toDateString() ?? now()->toDateString(),
                $rule->effective_to?->toDateString(),
                $rule->getKey(),
                true,
            );
        });
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
