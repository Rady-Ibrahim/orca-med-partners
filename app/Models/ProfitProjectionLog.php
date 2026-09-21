<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfitProjectionLog extends Model
{
    protected $table = 'profit_projection_logs';

    protected $fillable = [
        'participant_id',
        'amount',
        'period_type',
        'period_value',
        'is_compounded',
        'expected_net_profit',
        'expected_total_balance',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_compounded' => 'boolean',
            'expected_net_profit' => 'decimal:2',
            'expected_total_balance' => 'decimal:2',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
