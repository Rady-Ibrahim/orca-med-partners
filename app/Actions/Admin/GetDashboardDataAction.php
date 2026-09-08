<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\AuditLog;
use App\Models\DepreciationNote;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\FundTransaction;
use App\Models\Investment;
use App\Models\MonthlyProfit;
use App\Models\Notification;
use App\Models\Participant;
use App\Models\Settlement;
use App\Support\DecimalFormatter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class GetDashboardDataAction
{
    public function execute(int $year): array
    {
        $latestSnapshot = DB::table('capital_snapshots')->latest('snapshot_date')->first();
        $approvedProfit = MonthlyProfit::query()->where('year', $year)->where('status', 'approved');
        $approvedProfitTotal = (string) (clone $approvedProfit)->sum('gross_profit');
        $distributedTotal = (string) (clone $approvedProfit)->sum('distributed_amount');

        $monthlyProfits = (clone $approvedProfit)
            ->select('month', DB::raw('SUM(gross_profit) as gross_profit'), DB::raw('SUM(distributed_amount) as distributed_amount'))
            ->groupBy('month')->orderBy('month')->get()->keyBy('month');

        $monthlySeries = collect(range(1, 12))->map(function (int $month) use ($monthlyProfits): array {
            $row = $monthlyProfits->get($month);

            return [
                'label' => Carbon::create()->month($month)->locale('ar')->monthName,
                'gross' => (string) ($row->gross_profit ?? '0.00'),
                'distributed' => (string) ($row->distributed_amount ?? '0.00'),
            ];
        })->values()->all();
        $chartMax = '1.00';
        foreach ($monthlySeries as $point) {
            if (bccomp($point['gross'], $chartMax, 2) > 0) {
                $chartMax = $point['gross'];
            }
            if (bccomp($point['distributed'], $chartMax, 2) > 0) {
                $chartMax = $point['distributed'];
            }
        }

        $fundTransactionTotals = FundTransaction::query()
            ->select(
                'fund_id',
                DB::raw("SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE 0 END) as deposits"),
                DB::raw("SUM(CASE WHEN transaction_type = 'withdrawal' THEN amount ELSE 0 END) as withdrawals")
            )
            ->groupBy('fund_id')->get()->keyBy('fund_id');
        $lastFundTransactions = FundTransaction::query()->select('fund_id', 'transaction_date')->latest('id')->get()->unique('fund_id')->keyBy('fund_id');

        $funds = Fund::query()->withCount('transactions')->orderBy('code')->get()->map(function (Fund $fund) use ($fundTransactionTotals, $lastFundTransactions): array {
            $totals = $fundTransactionTotals->get($fund->id);
            $lastTransaction = $lastFundTransactions->get($fund->id);

            return [
                'name' => $fund->name,
                'code' => $fund->code,
                'balance' => (string) $fund->current_balance,
                'deposits' => (string) ($totals->deposits ?? '0.00'),
                'withdrawals' => (string) ($totals->withdrawals ?? '0.00'),
                'last_transaction' => $lastTransaction?->transaction_date?->format('Y-m-d'),
                'status' => $fund->status,
            ];
        })->all();

        $rule = DistributionRule::query()->where('status', 'active')->latest('effective_from')->first();
        $settlementQuery = Settlement::query()->where('year', $year)->whereIn('status', ['draft', 'approved', 'paid']);
        $investmentTotals = Investment::query()
            ->select('participant_id', DB::raw('SUM(amount) as total_amount'))
            ->groupBy('participant_id')->get()->keyBy('participant_id');
        $recentParticipants = Participant::query()->latest('created_at')->limit(6)->get()->map(function (Participant $participant) use ($investmentTotals): array {
            return [
                'name' => trim($participant->first_name . ' ' . $participant->last_name),
                'username' => $participant->username,
                'status' => $participant->status,
                'joined' => $participant->created_at?->format('Y-m-d'),
                'investment' => (string) ($investmentTotals->get($participant->id)->total_amount ?? '0.00'),
            ];
        })->all();

        return [
            'year' => $year,
            'kpis' => [
                'capital' => (string) ($latestSnapshot?->total_capital ?? '0.00'),
                'investments' => (string) Investment::query()->sum('amount'),
                'participants' => Participant::query()->where('status', 'active')->count(),
                'approved_profits' => $approvedProfitTotal,
                'amount_due' => (string) (clone $settlementQuery)->sum('amount_due'),
                'paid' => (string) (clone $settlementQuery)->sum('paid_amount'),
            ],
            'monthly_series' => $monthlySeries,
            'chart_max' => $chartMax,
            'distribution_rule' => $rule ? [
                'management' => (string) $rule->management_fee_rate,
                'depreciation' => (string) $rule->depreciation_fund_rate,
                'growth' => (string) $rule->growth_fund_rate,
                'incentive' => (string) $rule->incentive_fund_rate,
                'distributed' => (string) $rule->distributed_share_rate,
            ] : null,
            'funds' => $funds,
            'participants' => $recentParticipants,
            'profits' => MonthlyProfit::query()->latest('year')->latest('month')->limit(6)->get()->map(fn(MonthlyProfit $profit): array => [
                'period' => sprintf('%04d / %02d', $profit->year, $profit->month),
                'gross' => (string) $profit->gross_profit,
                'distributed' => (string) $profit->distributed_amount,
                'status' => $profit->status,
                'approved_at' => $profit->approved_at?->format('Y-m-d'),
            ])->all(),
            'settlements' => Settlement::query()->with('items')->latest('year')->limit(6)->get()->map(fn(Settlement $settlement): array => [
                'year' => $settlement->year,
                'profit' => (string) $settlement->participant_profit_share,
                'due' => (string) $settlement->amount_due,
                'paid' => (string) $settlement->paid_amount,
                'status' => $settlement->status,
                'participants' => $settlement->items->count(),
            ])->all(),
            'depreciation' => DepreciationNote::query()->latest('transaction_date')->limit(5)->get()->map(fn(DepreciationNote $note): array => [
                'amount' => (string) $note->amount,
                'rate' => (string) ($note->rate ?? '0.0000'),
                'date' => $note->transaction_date?->format('Y-m-d'),
                'period' => sprintf('%04d / %02d', $note->year, $note->month),
                'description' => $note->description,
            ])->all(),
            'attention' => [
                'draft_profits' => MonthlyProfit::query()->where('status', 'draft')->count(),
                'draft_settlements' => Settlement::query()->where('status', 'draft')->count(),
                'unread_notifications' => Notification::query()->where('is_read', false)->count(),
            ],
            'activities' => AuditLog::query()->latest('id')->limit(7)->get()->map(fn(AuditLog $log): array => [
                'action' => $log->action,
                'entity' => $log->auditable_type,
                'at' => Carbon::parse($log->created_at)->diffForHumans(),
            ])->all(),
            'fund_total' => (string) Fund::query()->sum('current_balance'),
            'distributed_total' => $distributedTotal,
        ];
    }
}
