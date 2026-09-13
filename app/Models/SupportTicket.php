<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicket extends Model
{
    protected $fillable = [
        'participant_id',
        'subject',
        'message',
        'category',
        'status',
        'resolved_by_admin_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}