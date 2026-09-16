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

    protected static function booted(): void
    {
        static::deleting(function (self $fund): void {
            if ($fund->transactions()->exists()) {
                throw new ImmutableFinancialRecordException('Funds with a transaction history cannot be deleted.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'current_balance' => 'decimal:2',
        ];
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
