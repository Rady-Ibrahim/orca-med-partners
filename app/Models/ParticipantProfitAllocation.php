<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParticipantProfitAllocation extends Model
{
    protected $table = 'participant_profit_allocations';

    protected $fillable = [
        'monthly_profit_id',
        'participant_id',
        'amount',
        'share_ratio',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'share_ratio' => 'decimal:4',
        ];
    }

    public function monthlyProfit(): BelongsTo
    {
        return $this->belongsTo(MonthlyProfit::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
