<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\SecurityAuditService;
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

    protected static function booted(): void
    {
        static::created(function (self $snapshot): void {
            app(SecurityAuditService::class)->log('capital_snapshot_created', $snapshot->created_by_admin_id ? Admin::query()->find($snapshot->created_by_admin_id) : null, 'capital_snapshot', $snapshot->id, ['new' => $snapshot->only(['snapshot_date', 'year', 'month', 'total_capital', 'status'])]);
        });

        static::updated(function (self $snapshot): void {
            app(SecurityAuditService::class)->log('capital_snapshot_updated', $snapshot->created_by_admin_id ? Admin::query()->find($snapshot->created_by_admin_id) : null, 'capital_snapshot', $snapshot->id, ['old' => $snapshot->getOriginal(), 'new' => $snapshot->only(['snapshot_date', 'year', 'month', 'total_capital', 'status'])]);
        });
    }

    public function syncItems(array $items, ?Admin $actor): void
    {
        $oldItems = $this->items->keyBy('participant_id');

        $totalCapital = '0.00';
        foreach ($items as $item) {
            $totalCapital = bcadd($totalCapital, number_format((float) $item['capital'], 2, '.', ''), 2);
        }

        $this->items()->delete();

        foreach ($items as $index => $item) {
            $capital = number_format((float) $item['capital'], 2, '.', '');
            $ratio = bccomp($totalCapital, '0.00', 2) === 0
                ? '0.0000'
                : bcdiv($capital, $totalCapital, 4);

            $created = CapitalSnapshotItem::query()->create([
                'capital_snapshot_id' => $this->id,
                'participant_id' => (int) $item['participant_id'],
                'participant_capital_snapshot' => $capital,
                'participant_ratio_snapshot' => $ratio,
                'calculation_metadata' => ['index' => $index],
            ]);

            $old = $oldItems->get((int) $item['participant_id']);
            if ($old === null || bccomp((string) $old->participant_capital_snapshot, $capital, 2) === 0) {
                continue;
            }

            app(SecurityAuditService::class)->log(
                'capital_snapshot_item_updated',
                $actor,
                'capital_snapshot_item',
                $created->id,
                [
                    'snapshot_id' => $this->id,
                    'participant_id' => (int) $item['participant_id'],
                    'old_capital' => (string) $old->participant_capital_snapshot,
                    'new_capital' => $capital,
                ],
            );
        }

        $this->total_capital = $totalCapital;
        $this->save();
    }

    public function items(): HasMany
    {
        return $this->hasMany(CapitalSnapshotItem::class);
    }
}
