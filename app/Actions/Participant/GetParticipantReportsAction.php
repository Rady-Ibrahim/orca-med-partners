<?php

declare(strict_types=1);

namespace App\Actions\Participant;

use App\Models\CapitalSnapshotItem;
use App\Models\DepreciationNote;
use App\Models\Investment;
use App\Models\Participant;
use App\Models\ParticipantFundAllocation;
use App\Models\ParticipantProfitAllocation;
use App\Models\SettlementItem;
use Illuminate\Support\Collection;

final class GetParticipantReportsAction
{
    public const TYPES = [
        'settlement_statement' => 'كشف التسوية السنوي',
        'annual_investment_summary' => 'الملخص الاستثماري السنوي',
    ];

    public function settleStatementYears(Participant $participant): array
    {
        return SettlementItem::query()
            ->where('participant_id', $participant->id)
            ->whereHas('settlement', fn($query) => $query->where('status', '!=', 'cancelled'))
            ->get()
            ->sortByDesc(fn($item) => $item->settlement?->year)
            ->values()
            ->pluck('settlement.year')
            ->all();
    }

    public function summaryYears(Participant $participant): array
    {
        $years = collect([]);

        $years = $years->merge(
            CapitalSnapshotItem::query()
                ->where('participant_id', $participant->id)
                ->whereHas('capitalSnapshot', fn($query) => $query->whereNotNull('year'))
                ->get()
                ->pluck('capitalSnapshot.year')
        );

        $years = $years->merge(
            ParticipantProfitAllocation::query()
                ->where('participant_id', $participant->id)
                ->whereHas('monthlyProfit', fn($query) => $query->where('status', 'approved')->whereNotNull('year'))
                ->get()
                ->pluck('monthlyProfit.year')
        );

        return $years->unique()->filter(static fn($year) => $year !== null)->sortDesc()->values()->all();
    }

    public function settleStatementRows(Participant $participant, ?int $year): Collection
    {
        return SettlementItem::query()
            ->with(['settlement' => fn($query) => $query->with('payments.receipt')])
            ->where('participant_id', $participant->id)
            ->whereHas('settlement', fn($query) => $query
                ->when($year !== null, fn($nested) => $nested->where('year', $year))
                ->where('status', '!=', 'cancelled'))
            ->latest('id')
            ->get()
            ->map(fn(SettlementItem $item): array => [
                'year' => $item->settlement?->year,
                'version' => $item->settlement?->version,
                'status' => $item->settlement?->status,
                'profit_share' => (string) $item->profit_share,
                'fund_share' => (string) $item->fund_share,
                'amount_due' => (string) $item->net_payable,
                'paid_amount' => (string) $item->paid_amount,
                'remaining' => bcsub((string) $item->net_payable, (string) $item->paid_amount, 2),
                'payment_status' => $item->payment_status,
                'payments' => $item->settlement?->payments
                    ->map(fn($payment): array => [
                        'amount' => (string) $payment->amount,
                        'paid_at' => $payment->paid_at?->toDateString(),
                        'payment_method' => $payment->payment_method,
                        'reference' => $payment->reference,
                        'receipt_available' => $payment->receipt()->exists(),
                    ])
                    ->values()
                    ->all(),
            ]);
    }

    public function summaryRows(Participant $participant, ?int $year): Collection
    {
        $rows = collect([]);

        $rows = $rows->merge(
            Investment::query()
                ->where('participant_id', $participant->id)
                ->when($year !== null, fn($query) => $query->whereYear('invested_at', $year))
                ->latest('invested_at')
                ->get()
                ->map(fn(Investment $investment): array => [
                    'date' => $investment->invested_at?->toDateString() ?? '—',
                    'category' => 'استثمار',
                    'description' => 'قيمة الاستثمار',
                    'amount' => (string) $investment->amount,
                    'status' => $investment->status,
                ])
        );

        $rows = $rows->merge(
            CapitalSnapshotItem::query()
                ->with('capitalSnapshot')
                ->where('participant_id', $participant->id)
                ->whereHas('capitalSnapshot', fn($query) => $query->when($year !== null, fn($nested) => $nested->where('year', $year)))
                ->latest('id')
                ->get()
                ->map(fn(CapitalSnapshotItem $item): array => [
                    'date' => $item->capitalSnapshot?->snapshot_date?->toDateString() ?? '—',
                    'category' => 'رأس المال',
                    'description' => sprintf('لقطة رأس المال %04d/%02d', $item->capitalSnapshot?->year, $item->capitalSnapshot?->month),
                    'amount' => (string) $item->participant_capital_snapshot,
                    'status' => '—',
                ])
        );

        $rows = $rows->merge(
            ParticipantProfitAllocation::query()
                ->with('monthlyProfit')
                ->where('participant_id', $participant->id)
                ->whereHas('monthlyProfit', fn($query) => $query
                    ->where('status', 'approved')
                    ->when($year !== null, fn($nested) => $nested->where('year', $year)))
                ->latest('id')
                ->get()
                ->map(fn(ParticipantProfitAllocation $allocation): array => [
                    'date' => $allocation->monthlyProfit?->approved_at?->toDateString() ?? '—',
                    'category' => 'ربح',
                    'description' => sprintf('توزيع أرباح %04d/%02d', $allocation->monthlyProfit?->year, $allocation->monthlyProfit?->month),
                    'amount' => (string) $allocation->amount,
                    'status' => 'approved',
                ])
        );

        $rows = $rows->merge(
            ParticipantFundAllocation::query()
                ->with(['fund', 'monthlyProfit'])
                ->where('participant_id', $participant->id)
                ->whereHas('monthlyProfit', fn($query) => $query->when($year !== null, fn($nested) => $nested->where('year', $year)))
                ->latest('id')
                ->get()
                ->map(fn(ParticipantFundAllocation $allocation): array => [
                    'date' => '—',
                    'category' => 'صندوق',
                    'description' => sprintf('%s (%s)', $allocation->fund?->name ?? '—', $allocation->allocation_type),
                    'amount' => (string) $allocation->amount,
                    'status' => '—',
                ])
        );

        $rows = $rows->merge(
            DepreciationNote::query()
                ->where('participant_id', $participant->id)
                ->when($year !== null, fn($query) => $query->where('year', $year))
                ->latest('transaction_date')
                ->get()
                ->map(fn(DepreciationNote $note): array => [
                    'date' => $note->transaction_date?->toDateString() ?? '—',
                    'category' => 'إهلاك',
                    'description' => $note->description ?? 'حركة إهلاك',
                    'amount' => $note->amount === null ? '0.00' : (string) $note->amount,
                    'status' => '—',
                ])
        );

        return $rows->sortByDesc('date')->values();
    }
}