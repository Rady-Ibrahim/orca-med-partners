<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Financial\Exceptions\ImmutableFinancialRecordException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementAdjustment extends Model
{
    protected $fillable = ['settlement_id', 'settlement_payment_id', 'type', 'direction', 'amount', 'reason', 'reference', 'created_by_admin_id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn(): never => throw new ImmutableFinancialRecordException('Settlement adjustments are immutable.'));
        static::deleting(fn(): never => throw new ImmutableFinancialRecordException('Settlement adjustments cannot be deleted.'));
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SettlementPayment::class, 'settlement_payment_id');
    }
}
