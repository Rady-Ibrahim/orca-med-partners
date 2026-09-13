<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementPaymentReceipt extends Model
{
    protected $fillable = [
        'settlement_payment_id',
        'file_path',
        'original_filename',
        'mime',
        'created_by_admin_id',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SettlementPayment::class, 'settlement_payment_id');
    }
}