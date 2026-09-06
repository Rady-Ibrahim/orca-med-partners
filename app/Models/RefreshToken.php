<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class RefreshToken extends Model
{
    protected $table = 'refresh_tokens';

    protected $fillable = [
        'tokenable_type',
        'tokenable_id',
        'family',
        'token_hash',
        'expires_at',
        'revoked_at',
        'replaced_by_token_id',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    public function replacedBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_token_id');
    }

    public function isActive(): bool
    {
        return is_null($this->revoked_at)
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
