<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SettlementPayment extends Model
{
    protected $fillable = [
        'settlement_id',
        'amount',
        'paid_at',
        'payment_method',
        'payment_source',
        'reference',
        'description',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(SettlementPaymentReceipt::class, 'settlement_payment_id');
    }
}
