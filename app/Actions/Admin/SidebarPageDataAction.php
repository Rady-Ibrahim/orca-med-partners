<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\CapitalSnapshot;
use App\Models\DepreciationNote;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\Investment;
use App\Models\MonthlyProfit;
use App\Models\Notification;
use App\Models\Participant;
use App\Models\Settlement;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

final class SidebarPageDataAction
{
    public function participants(): LengthAwarePaginator
    {
        return Participant::query()
            ->with('investments')
            ->latest('created_at')
            ->paginate(12)
            ->through(function (Participant $participant): array {
                $investment = $participant->investments()->sum('amount');

                return [
                    'name' => trim($participant->first_name . ' ' . $participant->last_name) ?: $participant->username,
                    'username' => $participant->username,
                    'status' => $participant->status,
                    'investment' => number_format((float) $investment, 2),
                    'joined' => $participant->created_at?->format('Y-m-d'),
                ];
            });
    }

    public function investments(): LengthAwarePaginator
    {
        return Investment::query()
            ->with('participant')
            ->latest('invested_at')
            ->paginate(12)
            ->through(function (Investment $investment): array {
                return [
                    'participant' => $investment->participant?->first_name . ' ' . $investment->participant?->last_name ?: $investment->participant?->username,
                    'amount' => number_format((float) $investment->amount, 2),
                    'status' => $investment->status,
                    'date' => $investment->invested_at?->format('Y-m-d'),
                    'notes' => $investment->notes ?: '—',
                ];
            });
    }

    public function capitalSnapshots(): LengthAwarePaginator
    {
        return CapitalSnapshot::query()
            ->latest('snapshot_date')
            ->paginate(12)
            ->through(function (CapitalSnapshot $snapshot): array {
                return [
                    'date' => $snapshot->snapshot_date?->format('Y-m-d'),
                    'year' => $snapshot->year,
                    'month' => $snapshot->month,
                    'total' => number_format((float) $snapshot->total_capital, 2),
                    'status' => $snapshot->status,
                ];
            });
    }

    public function monthlyProfits(): LengthAwarePaginator
    {
        return MonthlyProfit::query()
            ->with('distributionRule')
            ->latest('year')
            ->latest('month')
            ->paginate(12)
            ->through(function (MonthlyProfit $profit): array {
                return [
                    'period' => sprintf('%04d / %02d', $profit->year, $profit->month),
                    'gross' => number_format((float) $profit->gross_profit, 2),
                    'distributed' => number_format((float) $profit->distributed_amount, 2),
                    'status' => $profit->status,
                    'approved_at' => $profit->approved_at?->format('Y-m-d'),
                ];
            });
    }

    public function settlements(): LengthAwarePaginator
    {
        return Settlement::query()
            ->with('items')
            ->latest('year')
            ->paginate(12)
            ->through(function (Settlement $settlement): array {
                return [
                    'year' => $settlement->year,
                    'participants' => $settlement->items()->count(),
                    'annual_profit' => number_format((float) $settlement->participant_profit_share, 2),
                    'amount_due' => number_format((float) $settlement->amount_due, 2),
                    'paid_amount' => number_format((float) $settlement->paid_amount, 2),
                    'status' => $settlement->status,
                ];
            });
    }

    public function funds(): LengthAwarePaginator
    {
        return Fund::query()
            ->withCount('transactions')
            ->orderBy('code')
            ->paginate(12)
            ->through(function (Fund $fund): array {
                return [
                    'name' => $fund->name,
                    'code' => $fund->code,
                    'balance' => number_format((float) $fund->current_balance, 2),
                    'status' => $fund->status,
                    'transactions' => $fund->transactions_count,
                ];
            });
    }

    public function depreciationNotes(): LengthAwarePaginator
    {
        return DepreciationNote::query()
            ->with(['participant', 'fund'])
            ->latest('transaction_date')
            ->paginate(12)
            ->through(function (DepreciationNote $note): array {
                return [
                    'period' => sprintf('%04d / %02d', $note->year, $note->month),
                    'amount' => number_format((float) $note->amount, 2),
                    'rate' => $note->rate ? number_format((float) $note->rate * 100, 2) . '%' : '—',
                    'date' => $note->transaction_date?->format('Y-m-d'),
                    'description' => $note->description,
                    'fund' => $note->fund?->name ?? '—',
                ];
            });
    }

    public function reports(): array
    {
        return [
            'participants' => Participant::query()->count(),
            'investments' => Investment::query()->sum('amount'),
            'capital' => CapitalSnapshot::query()->latest('snapshot_date')->value('total_capital') ?? 0,
            'profits' => MonthlyProfit::query()->where('status', 'approved')->sum('gross_profit'),
            'settlements' => Settlement::query()->where('status', 'paid')->sum('paid_amount'),
            'funds' => Fund::query()->sum('current_balance'),
            'depreciation' => DepreciationNote::query()->sum('amount'),
        ];
    }

    public function notifications(): LengthAwarePaginator
    {
        return Notification::query()
            ->with('participant')
            ->latest('created_at')
            ->paginate(12)
            ->through(function (Notification $notification): array {
                return [
                    'title' => $notification->title,
                    'type' => $notification->type,
                    'body' => $notification->body,
                    'status' => $notification->is_read ? 'مقروء' : 'غير مقروء',
                    'created_at' => Carbon::parse($notification->created_at)->diffForHumans(),
                ];
            });
    }

    public function distributionRules(): LengthAwarePaginator
    {
        return DistributionRule::query()
            ->latest('effective_from')
            ->paginate(12)
            ->through(function (DistributionRule $rule): array {
                return [
                    'name' => $rule->notes ?: 'قاعدة توزيع',
                    'effective_from' => $rule->effective_from?->format('Y-m-d'),
                    'status' => $rule->status,
                    'management' => number_format((float) $rule->management_fee_rate * 100, 2) . '%',
                    'depreciation' => number_format((float) $rule->depreciation_fund_rate * 100, 2) . '%',
                    'distributed' => number_format((float) $rule->distributed_share_rate * 100, 2) . '%',
                ];
            });
    }

    public function settings(): LengthAwarePaginator
    {
        return AppSetting::query()
            ->latest('updated_at')
            ->paginate(12)
            ->through(function (AppSetting $setting): array {
                return [
                    'key' => $setting->key,
                    'description' => $setting->description ?: '—',
                    'updated_at' => $setting->updated_at?->format('Y-m-d H:i'),
                    'value' => is_array($setting->value) ? json_encode($setting->value) : (string) ($setting->value ?? '—'),
                ];
            });
    }

    public function auditLogs(): LengthAwarePaginator
    {
        return AuditLog::query()
            ->latest('id')
            ->paginate(12)
            ->through(function (AuditLog $log): array {
                return [
                    'actor' => $log->actor_type . ':' . $log->actor_id,
                    'action' => $log->action,
                    'entity' => $log->auditable_type,
                    'entity_id' => $log->auditable_id,
                    'created_at' => $log->created_at?->format('Y-m-d H:i'),
                ];
            });
    }
}
