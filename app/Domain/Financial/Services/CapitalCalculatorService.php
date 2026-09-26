<?php

declare(strict_types=1);

namespace App\Domain\Financial\Services;

use App\Domain\Financial\Exceptions\InvalidCapitalSnapshotException;
use App\Domain\Financial\ValueObjects\FinancialRoundingService;
use App\Models\CapitalSnapshot;

/**
 * Single source of truth for capital totals and ownership ratios.
 *
 * Every write path (capital page, admin API, reconciliation) must go through
 * this service so a participant's ownership ratio is never derived with a
 * different rounding rule in one place than in another.
 */
final class CapitalCalculatorService
{
    public function __construct(
        private FinancialRoundingService $rounding,
    ) {}

    /**
     * Normalises raw capital input coming from a form or API payload.
     *
     * @param  iterable<int, array{participant_id:int|string, capital:string|int|float}>  $items
     * @return array{items: array<int, array{participant_id:int, capital:string}>, total_capital: string}
     */
    public function normalizeItems(iterable $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $participantId = (int) $item['participant_id'];
            if ($participantId <= 0) {
                throw new InvalidCapitalSnapshotException('A capital row is missing a valid participant reference.');
            }

            $capital = $this->rounding->money($this->decimal($item['capital']));
            if (bccomp($capital, '0.00', 2) < 0) {
                throw new InvalidCapitalSnapshotException('Capital amounts cannot be negative.');
            }

            $normalized[$participantId] = ['participant_id' => $participantId, 'capital' => $capital];
        }

        $total = '0.00';
        foreach ($normalized as $item) {
            $total = bcadd($total, $item['capital'], 2);
        }

        return ['items' => array_values($normalized), 'total_capital' => $this->rounding->money($total)];
    }

    /**
     * Recomputes ownership ratios for a set of capitals.
     *
     * Ratios are rounded half-even to 4 decimals and the residual is applied to
     * the largest holder (ties broken by lowest participant id) so the returned
     * ratios always sum to exactly 1.0000. Truncating instead of rounding is
     * what previously let stored ratios drift to 0.9995.
     *
     * @param  array<int|string, string|int|float>  $capitals  participant id => capital
     * @return array<int, string> participant id => ratio
     */
    public function recalculateRatios(array $capitals): array
    {
        if ($capitals === []) {
            return [];
        }

        $amounts = [];
        $total = '0.00';
        foreach ($capitals as $participantId => $capital) {
            $amount = $this->rounding->money($this->decimal($capital));
            if (bccomp($amount, '0.00', 2) < 0) {
                throw new InvalidCapitalSnapshotException('Capital amounts cannot be negative.');
            }
            $amounts[(int) $participantId] = $amount;
            $total = bcadd($total, $amount, 2);
        }

        if (bccomp($total, '0.00', 2) === 0) {
            return array_map(static fn (): string => '0.0000', $amounts);
        }

        $ratios = [];
        $ratioSum = '0.0000';
        foreach ($amounts as $participantId => $amount) {
            $ratio = $this->rounding->rate(bcdiv($amount, $total, 8));
            $ratios[$participantId] = $ratio;
            $ratioSum = bcadd($ratioSum, $ratio, 4);
        }

        $delta = bcsub('1.0000', $ratioSum, 4);
        if (bccomp($delta, '0', 4) !== 0) {
            $carrier = $this->largestHolder($ratios);
            $adjusted = $this->rounding->rate(bcadd($ratios[$carrier], $delta, 4));

            if (bccomp($adjusted, '0', 4) < 0) {
                throw new InvalidCapitalSnapshotException('Ownership ratio correction produced a negative share.');
            }

            $ratios[$carrier] = $adjusted;
        }

        return $ratios;
    }

    /**
     * Repairs a stored snapshot in place: recomputes every ownership ratio and
     * the header total from the persisted capital values.
     *
     * Rows are updated rather than deleted and re-inserted so item ids stay
     * stable and keep pointing at the same audit trail.
     *
     * @return array{changed: bool, total_capital: string, updated: int}
     */
    public function recalculateSnapshot(CapitalSnapshot $snapshot): array
    {
        $items = $snapshot->items()->orderBy('participant_id')->get();
        if ($items->isEmpty()) {
            return ['changed' => false, 'total_capital' => $snapshot->total_capital, 'updated' => 0];
        }

        $capitals = [];
        foreach ($items as $item) {
            $capitals[(int) $item->participant_id] = (string) $item->participant_capital_snapshot;
        }

        $ratios = $this->recalculateRatios($capitals);

        $total = '0.00';
        $updated = 0;
        foreach ($items as $item) {
            $total = bcadd($total, (string) $item->participant_capital_snapshot, 2);
            $ratio = $ratios[(int) $item->participant_id];

            if (bccomp((string) $item->participant_ratio_snapshot, $ratio, 4) === 0) {
                continue;
            }

            $item->forceFill(['participant_ratio_snapshot' => $ratio])->saveQuietly();
            $updated++;
        }

        $total = $this->rounding->money($total);
        $changed = $updated > 0 || bccomp((string) $snapshot->total_capital, $total, 2) !== 0;

        if ($changed) {
            $snapshot->forceFill(['total_capital' => $total])->saveQuietly();
        }

        return ['changed' => $changed, 'total_capital' => $total, 'updated' => $updated];
    }

    /**
     * @param  array<int, string>  $ratios
     */
    private function largestHolder(array $ratios): int
    {
        $carrier = null;
        $best = '-1';

        foreach ($ratios as $participantId => $ratio) {
            $comparison = bccomp($ratio, $best, 4);
            if ($carrier === null || $comparison > 0 || ($comparison === 0 && $participantId < $carrier)) {
                $best = $ratio;
                $carrier = $participantId;
            }
        }

        return (int) $carrier;
    }

    private function decimal(string|int|float $value): string
    {
        if (is_int($value) || is_float($value)) {
            return number_format((float) $value, 8, '.', '');
        }

        $value = trim($value);
        if ($value === '') {
            return '0';
        }

        if (preg_match('/^-?\d+(?:\.\d+)?$/', $value) !== 1) {
            throw new InvalidCapitalSnapshotException('Capital amount is not a valid decimal value.');
        }

        return $value;
    }
}
