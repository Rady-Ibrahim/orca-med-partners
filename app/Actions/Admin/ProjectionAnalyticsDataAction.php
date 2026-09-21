<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\ProfitProjectionLog;
use App\Support\DecimalFormatter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ProjectionAnalyticsDataAction
{
    private const PERIOD_LABELS = [
        'month' => 'شهري',
        'quarter' => 'ربع سنوي',
        'semi_annual' => 'نصف سنوي',
        'annual' => 'سنوي',
        'years' => 'سنوات',
    ];

    private const PERIOD_COLORS = [
        'month' => '#38bdf8',
        'quarter' => '#818cf8',
        'semi_annual' => '#c084fc',
        'annual' => '#34d399',
        'years' => '#fbbf24',
    ];

    public function execute(array $filters = []): array
    {
        $base = $this->applyFilters(ProfitProjectionLog::query(), $filters);

        return [
            'kpis' => $this->kpis($base),
            'series' => $this->dailySeries($base),
            'distribution' => $this->distribution($base),
            'compounded_share' => $this->compoundedShare($base),
            'logs' => $this->logs($base),
        ];
    }

    public function periodLabel(string $periodType): string
    {
        return self::PERIOD_LABELS[$periodType] ?? $periodType;
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['period_type'])) {
            $query->where('period_type', $filters['period_type']);
        }

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function (Builder $q) use ($s): void {
                $q->where('amount', 'like', "%{$s}%")
                    ->orWhere('period_type', 'like', "%{$s}%")
                    ->orWhere('ip_address', 'like', "%{$s}%")
                    ->orWhereHas('participant', function (Builder $sub) use ($s): void {
                        $sub->where('first_name', 'like', "%{$s}%")
                            ->orWhere('last_name', 'like', "%{$s}%")
                            ->orWhere('username', 'like', "%{$s}%");
                    });
            });
        }

        return $query;
    }

    /**
     * @return array{
     *     total_searches: int,
     *     total_targeted_capital: string,
     *     total_targeted_capital_label: string,
     *     average_target_amount: string,
     *     most_popular_period: array{type: string, label: string, count: int}|null,
     * }
     */
    private function kpis(Builder $query): array
    {
        $total = (clone $query)->count();
        $rawCapital = (clone $query)->sum('amount');
        $totalCapital = DecimalFormatter::money($rawCapital);
        $average = $total > 0 ? bcdiv((string) $rawCapital, (string) $total, 2) : '0.00';

        $popular = (clone $query)
            ->select('period_type', DB::raw('count(*) as total'))
            ->groupBy('period_type')
            ->orderByDesc('total')
            ->first();

        return [
            'total_searches' => $total,
            'total_targeted_capital' => $totalCapital,
            'total_targeted_capital_label' => $totalCapital,
            'average_target_amount' => $average,
            'average_target_amount_label' => DecimalFormatter::money($average),
            'most_popular_period' => $popular ? [
                'type' => $popular->period_type,
                'label' => $this->periodLabel($popular->period_type),
                'count' => (int) $popular->total,
            ] : null,
        ];
    }

    /**
     * @return array{points: list<array{label: string, full_label: string, count: int, amount: string}>, max_count: string, max_amount: string}
     */
    private function dailySeries(Builder $query): array
    {
        $start = Carbon::today()->subDays(13);
        $rows = (clone $query)
            ->where('created_at', '>=', $start->copy()->startOfDay())
            ->select(DB::raw('date(created_at) as day'), DB::raw('count(*) as cnt'), DB::raw('sum(amount) as amt'))
            ->groupBy(DB::raw('date(created_at)'))
            ->get()
            ->keyBy('day');

        $points = [];
        $maxCount = 0;
        $maxAmount = '0';

        for ($i = 0; $i < 14; $i++) {
            $date = $start->copy()->addDays($i);
            $row = $rows->get($date->format('Y-m-d'));
            $count = (int) ($row->cnt ?? 0);
            $amount = (string) ($row->amt ?? '0');

            $maxCount = max($maxCount, $count);
            if (bccomp($amount, $maxAmount, 2) > 0) {
                $maxAmount = $amount;
            }

            $points[] = [
                'label' => $date->format('d/m'),
                'full_label' => $date->format('Y-m-d'),
                'count' => $count,
                'amount' => $amount,
            ];
        }

        return [
            'points' => $points,
            'max_count' => (string) $maxCount,
            'max_amount' => $maxAmount,
        ];
    }

    /**
     * @return array{items: list<array{type: string, label: string, count: int, color: string, share: string, percent: float}>, total: int}
     */
    private function distribution(Builder $query): array
    {
        $rows = (clone $query)
            ->select('period_type', DB::raw('count(*) as total'))
            ->groupBy('period_type')
            ->orderByDesc('total')
            ->get();

        $total = (int) $rows->sum('total');

        $items = $rows->map(fn ($row): array => [
            'type' => $row->period_type,
            'label' => $this->periodLabel($row->period_type),
            'count' => (int) $row->total,
            'color' => self::PERIOD_COLORS[$row->period_type] ?? '#94a3b8',
            'share' => DecimalFormatter::ratioPercent((string) $row->total, (string) $total),
            'percent' => $total > 0 ? round(((int) $row->total / $total) * 100, 2) : 0.0,
        ])->values()->all();

        return ['items' => $items, 'total' => $total];
    }

    /**
     * @return array{total: int, compounded: int, percent: string}
     */
    private function compoundedShare(Builder $query): array
    {
        $total = (clone $query)->count();
        $compounded = (clone $query)->where('is_compounded', true)->count();

        return [
            'total' => $total,
            'compounded' => $compounded,
            'percent' => DecimalFormatter::ratioPercent((string) $compounded, (string) $total),
        ];
    }

    private function logs(Builder $query): LengthAwarePaginator
    {
        return (clone $query)
            ->with('participant')
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(function (ProfitProjectionLog $log): array {
                return [
                    'id' => $log->getKey(),
                    'participant' => $log->participant
                        ? (trim(($log->participant->first_name ?? '').' '.($log->participant->last_name ?? '')) ?: $log->participant->username)
                        : 'زائر',
                    'participant_id' => $log->participant_id,
                    'amount' => DecimalFormatter::money($log->amount),
                    'period_type' => $log->period_type,
                    'period_label' => $this->periodLabel($log->period_type),
                    'period_value' => $log->period_value,
                    'is_compounded' => $log->is_compounded,
                    'expected_net_profit' => DecimalFormatter::money($log->expected_net_profit),
                    'expected_total_balance' => DecimalFormatter::money($log->expected_total_balance),
                    'ip_address' => $log->ip_address ?? '—',
                    'created_at' => Carbon::parse($log->created_at)->format('Y-m-d H:i'),
                ];
            });
    }
}
