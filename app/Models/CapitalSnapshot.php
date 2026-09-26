<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Financial\Services\CapitalCalculatorService;
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

    /**
     * Applies a capital edit from the capital page (or admin API).
     *
     * Capital entered here is authoritative and is deliberately independent of
     * the `investments` table. Rows are upserted rather than deleted and
     * re-inserted so capital_snapshot_item ids stay stable and keep pointing at
     * the same audit trail, and participants absent from the payload keep the
     * capital they already had.
     *
     * @param  array<int, array{participant_id:int, capital:string|int|float}>  $items
     */
    public function syncItems(array $items, ?Admin $actor): void
    {
        $calculator = app(CapitalCalculatorService::class);
        $normalized = $calculator->normalizeItems($items);

        $existing = $this->items()->get()->keyBy('participant_id');

        // Ratios are relative, so a partial payload still changes everyone
        // else's share. Merge the retained rows into the base before dividing,
        // otherwise the header total and the stored ratios silently disagree
        // with the item rows that were left untouched.
        $capitals = [];
        foreach ($existing as $participantId => $row) {
            $capitals[(int) $participantId] = (string) $row->participant_capital_snapshot;
        }

        foreach ($normalized['items'] as $item) {
            $capitals[$item['participant_id']] = $item['capital'];
        }

        $ratios = $calculator->recalculateRatios($capitals);

        foreach ($ratios as $participantId => $ratio) {
            $participantId = (int) $participantId;
            $capital = $capitals[$participantId];

            /** @var CapitalSnapshotItem $row */
            $row = $existing->get($participantId);

            if ($row === null) {
                $created = $this->items()->create([
                    'participant_id' => $participantId,
                    'participant_capital_snapshot' => $capital,
                    'participant_ratio_snapshot' => $ratio,
                    'calculation_metadata' => ['source' => 'capital_page'],
                ]);

                app(SecurityAuditService::class)->log(
                    'capital_snapshot_item_created',
                    $actor,
                    'capital_snapshot_item',
                    $created->id,
                    [
                        'snapshot_id' => $this->id,
                        'participant_id' => $participantId,
                        'new_capital' => $capital,
                        'new_ratio' => $ratio,
                    ],
                );

                continue;
            }

            $oldCapital = (string) $row->participant_capital_snapshot;
            $oldRatio = (string) $row->participant_ratio_snapshot;

            if (bccomp($oldCapital, $capital, 2) === 0 && bccomp($oldRatio, $ratio, 4) === 0) {
                continue;
            }

            $row->forceFill([
                'participant_capital_snapshot' => $capital,
                'participant_ratio_snapshot' => $ratio,
            ])->saveQuietly();

            app(SecurityAuditService::class)->log(
                'capital_snapshot_item_updated',
                $actor,
                'capital_snapshot_item',
                $row->id,
                [
                    'snapshot_id' => $this->id,
                    'participant_id' => $participantId,
                    'old_capital' => $oldCapital,
                    'new_capital' => $capital,
                    'old_ratio' => $oldRatio,
                    'new_ratio' => $ratio,
                ],
            );
        }

        $this->total_capital = array_reduce(
            $capitals,
            static fn (string $carry, string $capital): string => bcadd($carry, $capital, 2),
            '0.00',
        );
        $this->save();
    }

    /**
     * Recomputes this snapshot's ownership ratios and header total in place.
     *
     * @return array{changed: bool, total_capital: string, updated: int}
     */
    public function recalculateRatios(): array
    {
        return app(CapitalCalculatorService::class)->recalculateSnapshot($this);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CapitalSnapshotItem::class);
    }
}
