<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Investment extends Model
{
    protected $table = 'investments';

    protected $fillable = [
        'participant_id',
        'amount',
        'invested_at',
        'status',
        'notes',
        'created_by_admin_id',
        'approved_by_admin_id',
        'approved_at',
        'approved_by_admin_id',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'invested_at' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
