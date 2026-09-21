<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Financial\Exceptions\ImmutableFinancialRecordException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fund extends Model
{
    protected $table = 'funds';

    protected $fillable = [
        'code',
        'name',
        'current_balance',
        'status',
        'description',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'current_balance' => 'decimal:2',
        ];
    }

    public const SYSTEM_FUND_CODES = ['depreciation_fund', 'growth_fund', 'incentive_fund', 'management_fund'];

    public const SYSTEM_FUND_ALIASES = [
        'management_fund' => ['نسبه اداره راس المال', 'نسبة إدارة رأس المال', 'حساب الإدارة', 'حساب الادارة', 'صندوق الإدارة', 'صندوق الادارة'],
        'growth_fund' => ['صندوق معدل النمو', 'صندوق النمو', 'صندوق نمو', 'معدل النمو'],
        'incentive_fund' => ['صندوق حافز مشارك', 'صندوق الحوافز', 'صندوق الحافز', 'حافز المشاركين'],
        'depreciation_fund' => ['صندوق الاهلاك', 'صندوق الإهلاك', 'صندوق الاستهلاك'],
    ];

    public static function canonicalCodeOf(Fund $fund): ?string
    {
        if (in_array($fund->code, self::SYSTEM_FUND_CODES, true)) {
            return $fund->code;
        }

        $haystack = mb_strtolower($fund->code.' '.$fund->name);

        foreach (self::SYSTEM_FUND_ALIASES as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (str_contains($haystack, mb_strtolower($alias))) {
                    return $canonical;
                }
            }
        }

        return null;
    }

    public function isSystemFund(): bool
    {
        return self::canonicalCodeOf($this) !== null;
    }

    public static function resolveSystemFund(string $canonicalCode): ?Fund
    {
        return self::query()->get()->first(
            fn (Fund $fund): bool => self::canonicalCodeOf($fund) === $canonicalCode,
        );
    }

    protected static function booted(): void
    {
        static::deleting(function (Fund $fund): void {
            if ($fund->isSystemFund()) {
                throw new ImmutableFinancialRecordException('الصناديق البرمجية الأساسية لا يمكن حذفها.');
            }
        });
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FundTransaction::class)->orderBy('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }
}
