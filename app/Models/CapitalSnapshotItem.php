<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapitalSnapshotItem extends Model
{
    protected $table = 'capital_snapshot_items';

    protected $fillable = [
        'capital_snapshot_id',
        'participant_id',
        'participant_capital_snapshot',
        'participant_ratio_snapshot',
        'calculation_metadata',
    ];

    protected function casts(): array
    {
        return [
            'participant_capital_snapshot' => 'decimal:2',
            'participant_ratio_snapshot' => 'decimal:4',
            'calculation_metadata' => 'array',
        ];
    }

    public function capitalSnapshot(): BelongsTo
    {
        return $this->belongsTo(CapitalSnapshot::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
