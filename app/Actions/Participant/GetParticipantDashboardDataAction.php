<?php

declare(strict_types=1);

namespace App\Actions\Participant;

use App\Models\CapitalSnapshotItem;
use App\Models\DepreciationNote;
use App\Models\Investment;
use App\Models\Notification;
use App\Models\Participant;
use App\Models\ParticipantFundAllocation;
use App\Models\ParticipantProfitAllocation;
use App\Models\SettlementItem;
use App\Models\Settlement;
use App\Models\CapitalSnapshot;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class GetParticipantDashboardDataAction
{
    public function profile(Participant $participant): array
    {
        return ['id' => $participant->id, 'username' => $participant->username, 'name' => trim($participant->first_name . ' ' . $participant->last_name), 'email' => $participant->email, 'status' => $participant->status, 'participation_start_date' => $participant->created_at?->toDateString()];
    }

    public function investments(Participant $participant, array $filters): LengthAwarePaginator
    {
        return Investment::query()->where('participant_id', $participant->id)->when($filters['status'] ?? null, fn($query, $status) => $query->where('status', $status))->latest('invested_at')->paginate(20)->through(fn(Investment $investment): array => ['id' => $investment->id, 'amount' => (string) $investment->amount, 'invested_at' => $investment->invested_at?->toDateString(), 'status' => $investment->status, 'approved_at' => $investment->approved_at?->toISOString()])->withQueryString();
    }

    public function capital(Participant $participant, array $filters): LengthAwarePaginator
    {
        return CapitalSnapshotItem::query()->with('capitalSnapshot')->where('participant_id', $participant->id)->when($filters['year'] ?? null, fn($query, $year) => $query->whereHas('capitalSnapshot', fn($nested) => $nested->where('year', $year)))->latest('id')->paginate(20)->through(fn(CapitalSnapshotItem $item): array => ['id' => $item->id, 'snapshot_date' => $item->capitalSnapshot?->snapshot_date?->toDateString(), 'year' => $item->capitalSnapshot?->year, 'month' => $item->capitalSnapshot?->month, 'participant_capital' => (string) $item->participant_capital_snapshot, 'participant_ratio' => (string) $item->participant_ratio_snapshot])->withQueryString();
    }

    public function profits(Participant $participant, array $filters): LengthAwarePaginator
    {
        return ParticipantProfitAllocation::query()->with('monthlyProfit')->where('participant_id', $participant->id)->whereHas('monthlyProfit', function ($query) use ($filters): void {
            $query->where('status', 'approved')->when($filters['year'] ?? null, fn($nested, $year) => $nested->where('year', $year))->when($filters['month'] ?? null, fn($nested, $month) => $nested->where('month', $month));
        })->latest('id')->paginate(20)->through(fn(ParticipantProfitAllocation $allocation): array => ['id' => $allocation->id, 'year' => $allocation->monthlyProfit?->year, 'month' => $allocation->monthlyProfit?->month, 'gross_profit' => (string) $allocation->monthlyProfit?->gross_profit, 'amount' => (string) $allocation->amount, 'status' => $allocation->monthlyProfit?->status, 'approved_at' => $allocation->monthlyProfit?->approved_at?->toISOString()])->withQueryString();
    }

    public function funds(Participant $participant, array $filters): LengthAwarePaginator
    {
        return ParticipantFundAllocation::query()->with(['fund', 'monthlyProfit'])->where('participant_id', $participant->id)->when($filters['year'] ?? null, fn($query, $year) => $query->whereHas('monthlyProfit', fn($nested) => $nested->where('year', $year)))->latest('id')->paginate(20)->through(fn(ParticipantFundAllocation $allocation): array => ['id' => $allocation->id, 'fund' => ['id' => $allocation->fund?->id, 'code' => $allocation->fund?->code, 'name' => $allocation->fund?->name], 'amount' => (string) $allocation->amount, 'allocation_type' => $allocation->allocation_type, 'year' => $allocation->monthlyProfit?->year, 'month' => $allocation->monthlyProfit?->month])->withQueryString();
    }

    public function depreciation(Participant $participant, array $filters): LengthAwarePaginator
    {
        return DepreciationNote::query()->where('participant_id', $participant->id)->when($filters['year'] ?? null, fn($query, $year) => $query->where('year', $year))->when($filters['month'] ?? null, fn($query, $month) => $query->where('month', $month))->latest('transaction_date')->paginate(20)->through(fn(DepreciationNote $note): array => ['id' => $note->id, 'amount' => (string) $note->amount, 'rate' => $note->rate === null ? null : (string) $note->rate, 'transaction_date' => $note->transaction_date?->toDateString(), 'year' => $note->year, 'month' => $note->month, 'description' => $note->description, 'note' => $note->admin_note])->withQueryString();
    }

    public function settlements(Participant $participant, array $filters): LengthAwarePaginator
    {
        return SettlementItem::query()->with('settlement.payments')->where('participant_id', $participant->id)->when($filters['year'] ?? null, fn($query, $year) => $query->whereHas('settlement', fn($nested) => $nested->where('year', $year)))->when($filters['status'] ?? null, fn($query, $status) => $query->whereHas('settlement', fn($nested) => $nested->where('status', $status)))->latest('id')->paginate(20)->through(fn(SettlementItem $item): array => ['id' => $item->id, 'settlement_id' => $item->settlement_id, 'year' => $item->settlement?->year, 'status' => $item->settlement?->status, 'profit_share' => (string) $item->profit_share, 'fund_share' => (string) $item->fund_share, 'amount_due' => (string) $item->net_payable, 'paid_amount' => (string) $item->paid_amount, 'remaining' => bcsub((string) $item->net_payable, (string) $item->paid_amount, 2), 'payment_status' => $item->payment_status, 'paid_at' => $item->paid_at?->toISOString(), 'payments' => $item->settlement?->payments->map(fn($payment): array => ['id' => $payment->id, 'amount' => (string) $payment->amount, 'paid_at' => $payment->paid_at?->toISOString(), 'payment_method' => $payment->payment_method, 'reference' => $payment->reference, 'description' => $payment->description])->values()->all()])->withQueryString();
    }

    public function notifications(Participant $participant, array $filters): LengthAwarePaginator
    {
        return Notification::query()->where('participant_id', $participant->id)->when(array_key_exists('read', $filters), fn($query) => $query->where('is_read', (bool) $filters['read']))->latest('created_at')->paginate(20)->through(fn(Notification $notification): array => ['id' => $notification->id, 'type' => $notification->type, 'title' => $notification->title, 'message' => $notification->body, 'data' => is_array($notification->metadata) ? $notification->metadata : [], 'read' => $notification->is_read, 'created_at' => $notification->created_at?->toISOString()]);
    }

    public function settlement(Participant $participant, Settlement $settlement): array
    {
        $item = $settlement->items()->where('participant_id', $participant->id)->firstOrFail();
        $settlement->load(['payments', 'adjustments']);

        return [
            'id' => $settlement->id,
            'year' => $settlement->year,
            'version' => $settlement->version,
            'parent_id' => $settlement->parent_id,
            'status' => $settlement->status,
            'profit_share' => (string) $item->profit_share,
            'fund_share' => (string) $item->fund_share,
            'amount_due' => (string) $item->net_payable,
            'paid_amount' => (string) $item->paid_amount,
            'remaining' => bcsub((string) $item->net_payable, (string) $item->paid_amount, 2),
            'settlement_total_due' => (string) $settlement->amount_due,
            'settlement_total_paid' => (string) $settlement->paid_amount,
            'approved_at' => $settlement->approved_at?->toISOString(),
            'payout_at' => $settlement->payout_at?->toISOString(),
            'payments' => $settlement->payments->map(fn($payment): array => $this->payment($payment))->values()->all(),
            'adjustments' => $settlement->adjustments->map(fn($adjustment): array => ['id' => $adjustment->id, 'type' => $adjustment->type, 'direction' => $adjustment->direction, 'amount' => (string) $adjustment->amount, 'reason' => $adjustment->reason, 'created_at' => $adjustment->created_at?->toISOString()])->values()->all(),
        ];
    }

    public function settlementPayments(Participant $participant, Settlement $settlement): LengthAwarePaginator
    {
        abort_unless($settlement->items()->where('participant_id', $participant->id)->exists(), 404);

        return $settlement->payments()->paginate(20)->through(fn($payment): array => $this->payment($payment))->withQueryString();
    }

    public function capitalGrowth(array $filters): LengthAwarePaginator
    {
        $query = CapitalSnapshot::query()
            ->when($filters['year'] ?? null, fn($query, $year) => $query->where('year', $year))
            ->orderBy('snapshot_date')
            ->orderBy('id');

        $paginator = $query->paginate(20)->withQueryString();
        $rows = $paginator->getCollection();

        $baseline = '0.00';
        $first = $rows->first();
        if ($first !== null && $paginator->currentPage() > 1) {
            $baseline = (string) (CapitalSnapshot::query()
                ->when($filters['year'] ?? null, fn($query, $year) => $query->where('year', $year))
                ->where(fn($nested) => $nested->where('snapshot_date', '<', $first->snapshot_date)->orWhere(fn($sameDate) => $sameDate->where('snapshot_date', $first->snapshot_date)->where('id', '<', $first->id)))
                ->orderByDesc('snapshot_date')
                ->orderByDesc('id')
                ->value('total_capital') ?? '0.00');
        }

        $previous = $baseline;

        return $paginator->setCollection($rows->map(function (CapitalSnapshot $snapshot) use (&$previous): array {
            $current = (string) $snapshot->total_capital;
            $row = ['id' => $snapshot->id, 'snapshot_date' => $snapshot->snapshot_date?->toDateString(), 'year' => $snapshot->year, 'month' => $snapshot->month, 'snapshot_capital' => $current, 'movement' => bcsub($current, $previous, 2)];
            $previous = $current;
            return $row;
        }));
    }

    private function payment($payment): array
    {
        return ['id' => $payment->id, 'amount' => (string) $payment->amount, 'paid_at' => $payment->paid_at?->toISOString(), 'payment_method' => $payment->payment_method, 'payment_source' => $payment->payment_source, 'reference' => $payment->reference, 'description' => $payment->description];
    }
}
