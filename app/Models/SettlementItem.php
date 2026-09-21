<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettlementItem extends Model
{
    protected $table = 'settlement_items';

    protected $fillable = [
        'settlement_id',
        'participant_id',
        'profit_share',
        'fund_share',
        'net_payable',
        'payment_status',
        'paid_amount',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'profit_share' => 'decimal:2',
            'fund_share' => 'decimal:2',
            'net_payable' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
