<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\CapitalSnapshot;
use App\Models\DepreciationNote;
use App\Models\Fund;
use App\Models\FundTransaction;
use App\Models\Investment;
use App\Models\MonthlyProfit;
use App\Models\Participant;
use App\Models\ParticipantFundAllocation;
use App\Models\ParticipantProfitAllocation;
use App\Models\SettlementItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ReportDataAction
{
    public const REPORTS = [
        'participants',
        'investments',
        'capital',
        'monthly-profits',
        'annual-profits',
        'distribution',
        'funds',
        'fund-transactions',
        'fund-shares',
        'depreciation',
        'settlements',
        'due-paid',
        'capital-growth',
    ];

    public function execute(string $report, array $filters = [], bool $paginate = true): LengthAwarePaginator|Collection
    {
        $query = $this->query($report, $filters);

        return $paginate ? $query->paginate(20)->withQueryString() : $query->limit(5000)->get();
    }

    /** @return array<string, string>|null Aggregated totals row for numeric reports, or null when unsupported. */
    public function totals(string $report, array $filters = []): ?array
    {
        $summable = [
            'annual-profits' => ['gross_share', 'management_share', 'depreciation_share', 'growth_share', 'incentive_share', 'amount'],
            'monthly-profits' => ['gross_profit', 'management_amount', 'depreciation_amount', 'growth_amount', 'incentive_amount', 'distributed_amount'],
            'distribution' => ['management_amount', 'depreciation_amount', 'growth_amount', 'incentive_amount', 'distributed_amount'],
            'fund-shares' => ['growth_amount', 'incentive_amount', 'total_amount'],
            'settlements' => ['profit_share', 'fund_share', 'amount_due', 'paid_amount'],
            'due-paid' => ['amount_due', 'paid_amount', 'remaining'],
        ];

        $keys = $summable[$report] ?? null;
        if ($keys === null) {
            return null;
        }

        $rows = $this->normalize($this->query($report, $filters)->limit(5000)->get(), $report);

        $totals = [];
        $first = true;
        foreach (array_keys($this->columns($report)) as $key) {
            if (in_array($key, $keys, true)) {
                $sum = '0.00';
                foreach ($rows as $row) {
                    $value = (string) ($row[$key] ?? '0');
                    if (is_numeric($value)) {
                        $sum = bcadd($sum, $value, 2);
                    }
                }
                $totals[$key] = $this->moneyDecimal($sum);
            } elseif ($first) {
                $totals[$key] = 'الإجمالي الكلي';
                $first = false;
            } else {
                $totals[$key] = '—';
            }
        }

        if ($report === 'annual-profits') {
            $totals['ratio'] = '100.00%';
        }

        return $totals;
    }

    public function title(string $report): string
    {
        return [
            'participants' => 'تقرير المشاركين',
            'investments' => 'تقرير الاستثمارات',
            'capital' => 'تقرير رأس المال',
            'monthly-profits' => 'تقرير الأرباح الشهرية',
            'annual-profits' => 'تقرير الأرباح السنوية',
            'distribution' => 'تقرير التوزيعات',
            'funds' => 'تقرير الصناديق',
            'fund-transactions' => 'تقرير حركات الصناديق',
            'fund-shares' => 'تقرير حصص المشاركين في الصناديق (نمو + حافز)',
            'depreciation' => 'تقرير الإهلاك',
            'settlements' => 'تقرير التسويات السنوية',
            'due-paid' => 'تقرير المستحق والمدفوع',
            'capital-growth' => 'تقرير نمو رأس المال',
        ][$report] ?? 'تقرير مالي';
    }

    public function columns(string $report): array
    {
        return match ($report) {
            'participants' => ['name' => 'المشارك', 'username' => 'الرمز', 'capital' => 'رأس المال', 'ratio' => 'النسبة', 'status' => 'الحالة', 'created_at' => 'تاريخ البدء'],
            'investments' => ['participant' => 'المشارك', 'amount' => 'قيمة الاستثمار', 'status' => 'الحالة', 'invested_at' => 'تاريخ الاستثمار', 'approved_at' => 'تاريخ الاعتماد'],
            'capital' => ['snapshot_date' => 'تاريخ اللقطة', 'year' => 'السنة', 'month' => 'الشهر', 'total_capital' => 'إجمالي رأس المال', 'status' => 'الحالة'],
            'monthly-profits' => ['period' => 'الفترة', 'gross_profit' => 'إجمالي الربح', 'management_amount' => 'الإدارة', 'depreciation_amount' => 'الإهلاك', 'growth_amount' => 'النمو', 'incentive_amount' => 'الحافز', 'distributed_amount' => 'الموزع', 'status' => 'الحالة', 'approved_at' => 'تاريخ الاعتماد'],
            'annual-profits' => ['year' => 'السنة', 'participant' => 'المشارك', 'ratio' => 'النسبة', 'gross_share' => 'إجمالي الربح', 'management_share' => 'الإدارة', 'depreciation_share' => 'الإهلاك', 'growth_share' => 'النمو', 'incentive_share' => 'الحافز', 'amount' => 'الموزع للمشارك'],
            'distribution' => ['period' => 'الفترة', 'management_amount' => 'الإدارة', 'depreciation_amount' => 'الإهلاك', 'growth_amount' => 'النمو', 'incentive_amount' => 'الحافز', 'distributed_amount' => 'الموزع', 'rule_snapshot' => 'لقطة القاعدة'],
            'funds' => ['code' => 'الرمز', 'name' => 'الصندوق', 'current_balance' => 'الرصيد', 'status' => 'الحالة'],
            'fund-transactions' => ['fund' => 'الصندوق', 'transaction_type' => 'النوع', 'amount' => 'المبلغ', 'transaction_date' => 'التاريخ', 'description' => 'الوصف', 'created_by_admin_id' => 'أنشأ بواسطة'],
            'fund-shares' => ['year' => 'السنة', 'participant' => 'المشارك', 'growth_amount' => 'حصه في النمو', 'incentive_amount' => 'حصته في الحافز', 'total_amount' => 'الإجمالي'],
            'depreciation' => ['period' => 'الفترة', 'amount' => 'المبلغ', 'rate' => 'المعدل', 'transaction_date' => 'التاريخ', 'description' => 'الوصف', 'admin_note' => 'الملاحظة'],
            'settlements' => ['participant' => 'المشارك', 'year' => 'السنة', 'profit_share' => 'الربح السنوي', 'fund_share' => 'حصة الصندوق', 'amount_due' => 'المستحق', 'paid_amount' => 'المدفوع', 'status' => 'الحالة'],
            'due-paid' => ['participant' => 'المشارك', 'settlement_id' => 'التسوية', 'amount_due' => 'المستحق', 'paid_amount' => 'المدفوع', 'remaining' => 'المتبقي', 'payment_status' => 'الحالة'],
            'capital-growth' => ['period' => 'الفترة', 'previous_capital' => 'رأس المال السابق', 'current_capital' => 'رأس المال الحالي', 'movement' => 'الحركة'],
            default => [],
        };
    }

    private function query(string $report, array $filters): Builder
    {
        abort_unless(in_array($report, self::REPORTS, true), 404);

        return match ($report) {
            'participants' => Participant::query()
                ->select('participants.*')
                ->addSelect(DB::raw('(SELECT cs.total_capital FROM capital_snapshots cs JOIN capital_snapshot_items si ON si.capital_snapshot_id = cs.id WHERE si.participant_id = participants.id ORDER BY cs.snapshot_date DESC, cs.id DESC LIMIT 1) as snapshot_total'))
                ->addSelect(DB::raw('(SELECT si.participant_capital_snapshot FROM capital_snapshot_items si JOIN capital_snapshots cs ON cs.id = si.capital_snapshot_id WHERE si.participant_id = participants.id ORDER BY cs.snapshot_date DESC, cs.id DESC LIMIT 1) as capital_share'))
                ->latest('created_at')->when($filters['participant_id'] ?? null, fn (Builder $q, $id) => $q->whereKey($id))->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status)),
            'investments' => Investment::query()->with('participant')->latest('invested_at')->when($filters['participant_id'] ?? null, fn (Builder $q, $id) => $q->where('participant_id', $id))->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status)),
            'capital', 'capital-growth' => CapitalSnapshot::query()->when($filters['year'] ?? null, fn (Builder $q, $year) => $q->where('year', $year))->when($filters['month'] ?? null, fn (Builder $q, $month) => $q->where('month', $month))->orderBy('snapshot_date'),
            'monthly-profits', 'distribution' => MonthlyProfit::query()->with('distributionRule')->whereIn('status', ['draft', 'approved', 'superseded'])->when($filters['year'] ?? null, fn (Builder $q, $year) => $q->where('year', $year))->when($filters['month'] ?? null, fn (Builder $q, $month) => $q->where('month', $month))->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))->latest('year')->latest('month')->latest('version'),
            'annual-profits' => ParticipantProfitAllocation::query()->join('monthly_profits', 'monthly_profits.id', '=', 'participant_profit_allocations.monthly_profit_id')->join('participants', 'participants.id', '=', 'participant_profit_allocations.participant_id')->where('monthly_profits.status', 'approved')->when($filters['year'] ?? null, fn (Builder $q, $year) => $q->where('monthly_profits.year', $year))->when($filters['participant_id'] ?? null, fn (Builder $q, $id) => $q->where('participant_profit_allocations.participant_id', $id))->selectRaw('participant_profit_allocations.participant_id, monthly_profits.year, SUM(participant_profit_allocations.amount) as annual_amount, SUM(participant_profit_allocations.share_ratio * monthly_profits.gross_profit) as gross_share, SUM(participant_profit_allocations.share_ratio * monthly_profits.management_amount) as management_share, SUM(participant_profit_allocations.share_ratio * monthly_profits.depreciation_amount) as depreciation_share, SUM(participant_profit_allocations.share_ratio * monthly_profits.growth_amount) as growth_share, SUM(participant_profit_allocations.share_ratio * monthly_profits.incentive_amount) as incentive_share, SUM(participant_profit_allocations.share_ratio * monthly_profits.gross_profit) / NULLIF(SUM(monthly_profits.gross_profit), 0) as ratio, participants.first_name, participants.last_name, participants.username')->groupBy('participant_profit_allocations.participant_id', 'monthly_profits.year', 'participants.first_name', 'participants.last_name', 'participants.username')->orderByDesc('monthly_profits.year'),
            'funds' => Fund::query()->when($filters['status'] ?? null, fn (Builder $q, $status) => $q->where('status', $status))->orderBy('code'),
            'fund-transactions' => FundTransaction::query()->with(['fund', 'createdBy'])->when($filters['fund_id'] ?? null, fn (Builder $q, $id) => $q->where('fund_id', $id))->when($filters['transaction_type'] ?? null, fn (Builder $q, $type) => $q->where('transaction_type', $type))->latest('transaction_date')->latest('id'),
            'fund-shares' => ParticipantFundAllocation::query()->join('monthly_profits', 'monthly_profits.id', '=', 'participant_fund_allocations.monthly_profit_id')->join('participants', 'participants.id', '=', 'participant_fund_allocations.participant_id')->where('monthly_profits.status', 'approved')->whereIn('participant_fund_allocations.allocation_type', ['growth', 'incentive'])->when($filters['year'] ?? null, fn (Builder $q, $year) => $q->where('monthly_profits.year', $year))->when($filters['participant_id'] ?? null, fn (Builder $q, $id) => $q->where('participant_fund_allocations.participant_id', $id))->selectRaw('participant_fund_allocations.participant_id, monthly_profits.year, SUM(CASE WHEN participant_fund_allocations.allocation_type = ? THEN participant_fund_allocations.amount ELSE 0 END) as growth_amount, SUM(CASE WHEN participant_fund_allocations.allocation_type = ? THEN participant_fund_allocations.amount ELSE 0 END) as incentive_amount, SUM(participant_fund_allocations.amount) as total_amount, participants.first_name, participants.last_name, participants.username', ['growth', 'incentive'])->groupBy('participant_fund_allocations.participant_id', 'monthly_profits.year', 'participants.first_name', 'participants.last_name', 'participants.username')->orderByDesc('monthly_profits.year'),
            'depreciation' => DepreciationNote::query()->with(['participant', 'fund'])->when($filters['year'] ?? null, fn (Builder $q, $year) => $q->where('year', $year))->when($filters['month'] ?? null, fn (Builder $q, $month) => $q->where('month', $month))->latest('transaction_date'),
            'settlements', 'due-paid' => SettlementItem::query()->with(['participant', 'settlement'])->when($filters['participant_id'] ?? null, fn (Builder $q, $id) => $q->where('participant_id', $id))->whereHas('settlement', fn (Builder $q) => $q->when($filters['year'] ?? null, fn (Builder $nested, $year) => $nested->where('year', $year))->when($filters['status'] ?? null, fn (Builder $nested, $status) => $nested->where('status', $status)))->latest('id'),
        };
    }

    public function normalize(Collection $rows, string $report): Collection
    {
        $previousCapital = '0.00';

        return $rows->map(function ($row) use ($report, &$previousCapital): array {
            $participant = $row->participant ?? null;
            $name = $participant ? trim($participant->first_name.' '.$participant->last_name) : null;

            return match ($report) {
                'participants' => ['name' => trim($row->first_name.' '.$row->last_name) ?: $row->username, 'username' => $row->username, 'capital' => $this->moneyDecimal($row->capital_share), 'ratio' => $this->ratioPercent($row->capital_share, $row->snapshot_total), 'status' => $this->statusLabel($row->status), 'created_at' => optional($row->created_at)->format('Y-m-d')],
                'investments' => ['participant' => $name ?: $participant?->username, 'amount' => (string) $row->amount, 'status' => $this->statusLabel($row->status), 'invested_at' => optional($row->invested_at)->format('Y-m-d'), 'approved_at' => optional($row->approved_at)->format('Y-m-d H:i')],
                'capital' => ['snapshot_date' => optional($row->snapshot_date)->format('Y-m-d'), 'year' => $row->year, 'month' => $row->month, 'total_capital' => (string) $row->total_capital, 'status' => $this->statusLabel($row->status)],
                'monthly-profits' => ['period' => sprintf('%04d/%02d v%d', $row->year, $row->month, $row->version), 'gross_profit' => (string) $row->gross_profit, 'management_amount' => (string) $row->management_amount, 'depreciation_amount' => (string) $row->depreciation_amount, 'growth_amount' => (string) $row->growth_amount, 'incentive_amount' => (string) $row->incentive_amount, 'distributed_amount' => (string) $row->distributed_amount, 'status' => $this->statusLabel($row->status), 'approved_at' => optional($row->approved_at)->format('Y-m-d H:i')],
                'annual-profits' => ['year' => $row->year, 'participant' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')) ?: ($row->username ?? null), 'ratio' => $this->percentFromFactor($row->ratio), 'gross_share' => $this->moneyDecimal($row->gross_share), 'management_share' => $this->moneyDecimal($row->management_share), 'depreciation_share' => $this->moneyDecimal($row->depreciation_share), 'growth_share' => $this->moneyDecimal($row->growth_share), 'incentive_share' => $this->moneyDecimal($row->incentive_share), 'amount' => $this->moneyDecimal($row->annual_amount)],
                'distribution' => ['period' => sprintf('%04d/%02d', $row->year, $row->month), 'management_amount' => (string) $row->management_amount, 'depreciation_amount' => (string) $row->depreciation_amount, 'growth_amount' => (string) $row->growth_amount, 'incentive_amount' => (string) $row->incentive_amount, 'distributed_amount' => (string) $row->distributed_amount, 'rule_snapshot' => $this->distributionRuleLabel($row->distribution_rule_snapshot)],
                'funds' => ['code' => $row->code, 'name' => $row->name, 'current_balance' => (string) $row->current_balance, 'status' => $this->statusLabel($row->status)],
                'fund-transactions' => ['fund' => $row->fund?->name, 'transaction_type' => $this->transactionTypeLabel($row->transaction_type), 'amount' => (string) $row->amount, 'transaction_date' => optional($row->transaction_date)->format('Y-m-d'), 'description' => $row->description ?: $row->notes, 'created_by_admin_id' => $row->created_by_admin_id],
                'fund-shares' => ['year' => $row->year, 'participant' => trim(($row->first_name ?? '').' '.($row->last_name ?? '')) ?: ($row->username ?? null), 'growth_amount' => $this->moneyDecimal($row->growth_amount), 'incentive_amount' => $this->moneyDecimal($row->incentive_amount), 'total_amount' => $this->moneyDecimal($row->total_amount)],
                'depreciation' => ['period' => sprintf('%04d/%02d', $row->year, $row->month), 'amount' => (string) $row->amount, 'rate' => $this->percentLabel($row->rate), 'transaction_date' => optional($row->transaction_date)->format('Y-m-d'), 'description' => $row->description, 'admin_note' => $row->admin_note],
                'settlements' => ['participant' => $name ?: $participant?->username, 'year' => $row->settlement?->year, 'profit_share' => (string) $row->profit_share, 'fund_share' => (string) $row->fund_share, 'amount_due' => (string) $row->net_payable, 'paid_amount' => (string) $row->paid_amount, 'status' => $this->statusLabel($row->settlement?->status)],
                'due-paid' => ['participant' => $name ?: $participant?->username, 'settlement_id' => $row->settlement_id, 'amount_due' => (string) $row->net_payable, 'paid_amount' => (string) $row->paid_amount, 'remaining' => bcsub((string) $row->net_payable, (string) $row->paid_amount, 2), 'payment_status' => $this->statusLabel($row->payment_status)],
                'capital-growth' => $this->capitalGrowthRow($row, $previousCapital),
                default => [],
            };
        });
    }

    private function moneyDecimal(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function statusLabel(?string $status): ?string
    {
        return [
            'draft' => 'مسودة',
            'active' => 'نشط',
            'inactive' => 'غير نشط',
            'locked' => 'مقفل',
            'approved' => 'معتمد',
            'pending' => 'قيد الانتظار',
            'rejected' => 'مرفوض',
            'superseded' => 'مستبدل',
            'paid' => 'مدفوع',
            'partially_paid' => 'مدفوع جزئياً',
            'partial' => 'مدفوع جزئياً',
            'cancelled' => 'ملغي',
            'canceled' => 'ملغي',
        ][$status ?? ''] ?? $status;
    }

    private function transactionTypeLabel(?string $type): ?string
    {
        return match ($type) {
            'deposit' => 'إيداع',
            'withdrawal' => 'سحب',
            'adjustment' => 'تسوية',
            default => $type,
        };
    }

    /** @param array<string, string>|null $snapshot */
    private function distributionRuleLabel(?array $snapshot): string
    {
        if (empty($snapshot)) {
            return '—';
        }

        $labels = [
            'management_fee_rate' => 'الإدارة',
            'depreciation_fund_rate' => 'صندوق الإهلاك',
            'growth_fund_rate' => 'صندوق النمو',
            'incentive_fund_rate' => 'صندوق الحافز',
            'distributed_share_rate' => 'الحصة الموزعة',
        ];

        $parts = [];
        foreach ($snapshot as $key => $value) {
            $parts[] = ($labels[$key] ?? $key).' '.$this->percentLabel($value);
        }

        return implode(' · ', $parts);
    }

    private function percentLabel(mixed $value): string
    {
        $normalized = (string) ($value ?? '0');

        if (bccomp($normalized, '1', 4) <= 0) {
            $normalized = bcmul($normalized, '100', 4);
        }

        $formatted = number_format((float) $normalized, 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted.'%';
    }

    private function ratioPercent(mixed $value, mixed $total): string
    {
        if (bccomp((string) ($total ?? '0'), '0', 2) === 0) {
            return '0.00%';
        }

        $percent = bcmul(bcdiv((string) ($value ?? '0'), (string) $total, 6), '100', 2);

        return number_format((float) $percent, 2, '.', '').'%';
    }

    private function percentFromFactor(mixed $factor): string
    {
        return number_format((float) bcmul((string) ($factor ?? '0'), '100', 4), 2, '.', '').'%';
    }

    private function capitalGrowthRow(object $row, string &$previousCapital): array
    {
        $current = (string) $row->total_capital;
        $result = ['period' => sprintf('%04d/%02d', $row->year, $row->month), 'previous_capital' => $previousCapital, 'current_capital' => $current, 'movement' => bcsub($current, $previousCapital, 2)];
        $previousCapital = $current;

        return $result;
    }
}
