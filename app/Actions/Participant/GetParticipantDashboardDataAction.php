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
        return SettlementItem::query()->with('settlement')->where('participant_id', $participant->id)->when($filters['year'] ?? null, fn($query, $year) => $query->whereHas('settlement', fn($nested) => $nested->where('year', $year)))->when($filters['status'] ?? null, fn($query, $status) => $query->whereHas('settlement', fn($nested) => $nested->where('status', $status)))->latest('id')->paginate(20)->through(fn(SettlementItem $item): array => ['id' => $item->id, 'settlement_id' => $item->settlement_id, 'year' => $item->settlement?->year, 'status' => $item->settlement?->status, 'profit_share' => (string) $item->profit_share, 'fund_share' => (string) $item->fund_share, 'amount_due' => (string) $item->net_payable, 'paid_amount' => (string) $item->paid_amount, 'payment_status' => $item->payment_status, 'paid_at' => $item->paid_at?->toISOString()])->withQueryString();
    }

    public function notifications(Participant $participant, array $filters): LengthAwarePaginator
    {
        return Notification::query()->where('participant_id', $participant->id)->when(array_key_exists('read', $filters), fn($query) => $query->where('is_read', (bool) $filters['read']))->latest('created_at')->paginate(20)->through(fn(Notification $notification): array => ['id' => $notification->id, 'type' => $notification->type, 'title' => $notification->title, 'message' => $notification->body, 'data' => is_array($notification->metadata) ? $notification->metadata : [], 'read' => $notification->is_read, 'created_at' => $notification->created_at?->toISOString()]);
    }
}
