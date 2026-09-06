<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CapitalSnapshot extends Model
{
    protected $table = 'capital_snapshots';

    protected $fillable = [
        'snapshot_date',
        'year',
        'month',
        'total_capital',
        'status',
        'snapshot_metadata',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'total_capital' => 'decimal:2',
            'snapshot_metadata' => 'array',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CapitalSnapshotItem::class);
    }
}
