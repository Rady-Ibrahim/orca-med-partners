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
use App\Support\DecimalFormatter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

final class SidebarPageDataAction
{
    public function participants(array $filters = []): LengthAwarePaginator
    {
        $query = Participant::query()
            ->withSum('investments', 'amount')
            ->latest('created_at');

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('first_name', 'like', "%{$s}%")
                    ->orWhere('last_name', 'like', "%{$s}%")
                    ->orWhere('username', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%");
            });
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate(15)->withQueryString()->through(function (Participant $participant): array {
            return [
                'id' => $participant->getKey(),
                'name' => trim($participant->first_name.' '.$participant->last_name) ?: $participant->username,
                'first_name' => $participant->first_name,
                'last_name' => $participant->last_name,
                'username' => $participant->username,
                'email' => $participant->email ?? '—',
                'status' => $participant->status,
                'investment' => DecimalFormatter::money($participant->investments_sum_amount ?? '0'),
                'joined' => $participant->created_at?->format('Y-m-d'),
                'edit_payload' => [
                    'first_name' => $participant->first_name,
                    'last_name' => $participant->last_name,
                    'username' => $participant->username,
                    'email' => $participant->email ?? '',
                    'status' => $participant->status,
                ],
            ];
        });
    }

    public function investments(array $filters = []): LengthAwarePaginator
    {
        $query = Investment::query()->with('participant')->latest('invested_at');

        if (! empty($filters['participant'])) {
            $s = $filters['participant'];
            $query->whereHas('participant', function ($q) use ($s) {
                $q->where('first_name', 'like', "%{$s}%")
                    ->orWhere('last_name', 'like', "%{$s}%")
                    ->orWhere('username', 'like', "%{$s}%");
            });
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['date_from'])) {
            $query->where('invested_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->where('invested_at', '<=', $filters['date_to']);
        }

        return $query->paginate(15)->withQueryString()->through(function (Investment $investment): array {
            return [
                'id' => $investment->getKey(),
                'participant' => trim(($investment->participant?->first_name ?? '').' '.($investment->participant?->last_name ?? '')) ?: ($investment->participant?->username ?? '—'),
                'participant_id' => $investment->participant_id,
                'amount' => DecimalFormatter::money($investment->amount),
                'amount_raw' => (string) $investment->amount,
                'status' => $investment->status,
                'date' => $investment->invested_at?->format('Y-m-d'),
                'invested_at' => $investment->invested_at?->format('Y-m-d'),
                'notes' => $investment->notes ?: '—',
                'notes_raw' => $investment->notes ?? '',
                'edit_payload' => [
                    'participant_id' => $investment->participant_id,
                    'amount' => (string) $investment->amount,
                    'invested_at' => $investment->invested_at?->format('Y-m-d'),
                    'notes' => $investment->notes ?? '',
                    'status' => $investment->status,
                ],
            ];
        });
    }

    public function capitalSnapshots(array $filters = []): LengthAwarePaginator
    {
        $query = CapitalSnapshot::query()->with('items')->latest('snapshot_date');

        if (! empty($filters['year'])) {
            $query->where('year', $filters['year']);
        }
        if (! empty($filters['month'])) {
            $query->where('month', $ilters['month']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate(15)->withQueryString()->through(function (CapitalSnapshot $snapshot): array {
            return [
                'id' => $snapshot->getKey(),
                'date' => $snapshot->snapshot_date?->format('Y-m-d'),
                'year' => $snapshot->year,
                'month' => $snapshot->month,
                'total' => DecimalFormatter::money($snapshot->total_capital),
                'total_raw' => (string) $snapshot->total_capital,
                'status' => $snapshot->status,
                'items' => $snapshot->items->map(fn ($item): array => [
                    'participant_id' => $item->participant_id,
                    'capital' => (string) $item->participant_capital_snapshot,
                ])->values()->toArray(),
                'edit_payload' => [
                    'snapshot_date' => $snapshot->snapshot_date?->format('Y-m-d'),
                    'year' => $snapshot->year,
                    'month' => $snapshot->month,
                    'total_capital' => (string) $snapshot->total_capital,
                ],
            ];
        });
    }

    public function monthlyProfits(array $filters = []): LengthAwarePaginator
    {
        $query = MonthlyProfit::query()->with('distributionRule')->orderByDesc('year')->orderByDesc('month');

        if (! empty($filters['year'])) {
            $query->where('year', $filters['year']);
        }
        if (! empty($filters['month'])) {
            $query->where('month', $filters['month']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate(15)->withQueryString()->through(function (MonthlyProfit $profit): array {
            return [
                'id' => $profit->getKey(),
                'period' => sprintf('%04d / %02d', $profit->year, $profit->month),
                'gross' => DecimalFormatter::money($profit->gross_profit),
                'gross_raw' => (string) $profit->gross_profit,
                'distributed' => DecimalFormatter::money($profit->distributed_amount),
                'management' => DecimalFormatter::money($profit->management_amount),
                'status' => $profit->status,
                'approved_at' => $profit->approved_at?->format('Y-m-d'),
            ];
        });
    }

    public function settlements(array $filters = []): LengthAwarePaginator
    {
        $query = Settlement::query()->with('items')->orderByDesc('year');

        if (! empty($filters['year'])) {
            $query->where('year', $filters['year']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate(15)->withQueryString()->through(function (Settlement $settlement): array {
            return [
                'id' => $settlement->getKey(),
                'year' => $settlement->year,
                'participants' => $settlement->items()->count(),
                'annual_profit' => DecimalFormatter::money($settlement->participant_profit_share),
                'amount_due' => DecimalFormatter::money($settlement->amount_due),
                'amount_due_raw' => (string) $settlement->amount_due,
                'paid_amount' => DecimalFormatter::money($settlement->paid_amount),
                'status' => $settlement->status,
            ];
        });
    }

    public function funds(array $filters = []): LengthAwarePaginator
    {
        $query = Fund::query()
            ->withCount('transactions')
            ->with(['transactions' => fn ($q) => $q->orderBy('id')])
            ->orderBy('code');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%");
            });
        }

        return $query->paginate(15)->withQueryString()->through(function (Fund $fund): array {
            return [
                'id' => $fund->getKey(),
                'name' => $fund->name,
                'code' => $fund->code,
                'balance' => DecimalFormatter::money($fund->current_balance),
                'status' => $fund->status,
                'transactions' => $fund->transactions_count,
                'description' => $fund->description ?? '',
                'system_group' => in_array($fund->code, ['depreciation_fund', 'growth_fund', 'incentive_fund'], true),
                'transaction_items' => $fund->transactions->map(fn ($t): array => [
                    'id' => $t->getKey(),
                    'type' => $t->transaction_type,
                    'amount' => DecimalFormatter::money($t->amount),
                    'amount_raw' => (string) $t->amount,
                    'resulting_balance' => DecimalFormatter::money($t->resulting_balance),
                    'date' => $t->transaction_date?->format('Y-m-d') ?? '—',
                    'reference' => $t->reference ?? '',
                    'description' => $t->description ?? '',
                    'notes' => $t->notes ?? '',
                    'linked' => $t->monthly_profit_id !== null,
                    'edit_payload' => [
                        'reference' => $t->reference ?? '',
                        'description' => $t->description ?? '',
                        'notes' => $t->notes ?? '',
                        'transaction_date' => $t->transaction_date?->format('Y-m-d'),
                    ],
                ])->values()->all(),
                'edit_payload' => [
                    'name' => $fund->name,
                    'code' => $fund->code,
                    'status' => $fund->status,
                    'description' => $fund->description ?? '',
                ],
            ];
        });
    }

    public function depreciationNotes(array $filters = []): LengthAwarePaginator
    {
        $query = DepreciationNote::query()->with(['participant', 'fund'])->latest('transaction_date');

        if (! empty($filters['year'])) {
            $query->where('year', $filters['year']);
        }
        if (! empty($filters['month'])) {
            $query->where('month', $filters['month']);
        }
        if (! empty($filters['fund'])) {
            $query->whereHas('fund', function ($q) use ($filters) {
                $q->where('code', $filters['fund'])->orWhere('name', 'like', "%{$filters['fund']}%");
            });
        }

        return $query->paginate(15)->withQueryString()->through(function (DepreciationNote $note): array {
            return [
                'id' => $note->getKey(),
                'period' => sprintf('%04d / %02d', $note->year, $note->month),
                'amount' => DecimalFormatter::money($note->amount),
                'amount_raw' => (string) $note->amount,
                'rate' => $note->rate ? DecimalFormatter::percent($note->rate) : '—',
                'rate_raw' => (string) $note->rate,
                'date' => $note->transaction_date?->format('Y-m-d'),
                'transaction_date' => $note->transaction_date?->format('Y-m-d'),
                'description' => $note->description ?? '',
                'fund' => $note->fund?->name ?? '—',
                'fund_id' => $note->fund_id,
                'participant_id' => $note->participant_id,
                'year' => $note->year,
                'month' => $note->month,
                'edit_payload' => [
                    'amount' => (string) $note->amount,
                    'rate' => $note->rate ? rtrim(rtrim(bcmul((string) $note->rate, '100', 4), '0'), '.') : '0',
                    'transaction_date' => $note->transaction_date?->format('Y-m-d'),
                    'year' => $note->year,
                    'month' => $note->month,
                    'description' => $note->description ?? '',
                    'fund_id' => $note->fund_id,
                    'participant_id' => $note->participant_id,
                ],
            ];
        });
    }

    public function reports(): array
    {
        return [
            'participants' => Participant::query()->count(),
            'investments' => (string) Investment::query()->sum('amount'),
            'capital' => CapitalSnapshot::query()->latest('snapshot_date')->value('total_capital') ?? 0,
            'profits' => (string) MonthlyProfit::query()->where('status', 'approved')->sum('gross_profit'),
            'settlements' => (string) Settlement::query()->where('status', 'paid')->sum('paid_amount'),
            'funds' => (string) Fund::query()->sum('current_balance'),
            'depreciation' => (string) DepreciationNote::query()->sum('amount'),
        ];
    }

    public function notifications(array $filters = []): LengthAwarePaginator
    {
        $query = Notification::query()->with('participant')->latest('created_at');

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (isset($filters['is_read']) && $filters['is_read'] !== '') {
            $query->where('is_read', (bool) $filters['is_read']);
        }
        if (! empty($filters['participant'])) {
            $s = $filters['participant'];
            $query->whereHas('participant', function ($q) use ($s) {
                $q->where('first_name', 'like', "%{$s}%")
                    ->orWhere('username', 'like', "%{$s}%");
            });
        }

        return $query->paginate(15)->withQueryString()->through(function (Notification $notification): array {
            return [
                'title' => $notification->title,
                'type' => $notification->type,
                'body' => $notification->body,
                'is_read' => $notification->is_read,
                'status' => $notification->is_read ? 'مقروء' : 'غير مقروء',
                'participant' => $notification->participant ? trim(($notification->participant->first_name ?? '').' '.($notification->participant->last_name ?? '')) : '—',
                'created_at' => Carbon::parse($notification->created_at)->diffForHumans(),
            ];
        });
    }

    public function distributionRules(array $filters = []): LengthAwarePaginator
    {
        $query = DistributionRule::query()->latest('effective_from');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['year'])) {
            $query->whereYear('effective_from', $filters['year']);
        }

        return $query->paginate(15)->withQueryString()->through(function (DistributionRule $rule): array {
            return [
                'id' => $rule->getKey(),
                'name' => $rule->notes ?: 'قاعدة توزيع',
                'effective_from' => $rule->effective_from?->format('Y-m-d'),
                'effective_to' => $rule->effective_to?->format('Y-m-d') ?? 'مفتوحة',
                'effective_to_raw' => $rule->effective_to?->format('Y-m-d'),
                'status' => $rule->status,
                'management' => DecimalFormatter::percent($rule->management_fee_rate),
                'depreciation' => DecimalFormatter::percent($rule->depreciation_fund_rate),
                'growth' => DecimalFormatter::percent($rule->growth_fund_rate),
                'incentive' => DecimalFormatter::percent($rule->incentive_fund_rate),
                'distributed' => DecimalFormatter::percent($rule->distributed_share_rate),
                'management_raw' => (string) $rule->management_fee_rate,
                'depreciation_raw' => (string) $rule->depreciation_fund_rate,
                'growth_raw' => (string) $rule->growth_fund_rate,
                'incentive_raw' => (string) $rule->incentive_fund_rate,
                'distributed_raw' => (string) $rule->distributed_share_rate,
                'notes' => $rule->notes,
                'is_default' => $rule->is_default,
                'edit_payload' => [
                    'effective_from' => $rule->effective_from?->format('Y-m-d'),
                    'effective_to' => $rule->effective_to?->format('Y-m-d'),
                    'management_fee_rate' => rtrim(rtrim(bcmul((string) $rule->management_fee_rate, '100', 2), '0'), '.'),
                    'depreciation_fund_rate' => rtrim(rtrim(bcmul((string) $rule->depreciation_fund_rate, '100', 2), '0'), '.'),
                    'growth_fund_rate' => rtrim(rtrim(bcmul((string) $rule->growth_fund_rate, '100', 2), '0'), '.'),
                    'incentive_fund_rate' => rtrim(rtrim(bcmul((string) $rule->incentive_fund_rate, '100', 2), '0'), '.'),
                    'distributed_share_rate' => rtrim(rtrim(bcmul((string) $rule->distributed_share_rate, '100', 2), '0'), '.'),
                    'status' => $rule->status,
                    'is_default' => $rule->is_default,
                    'notes' => $rule->notes,
                ],
            ];
        });
    }

    public function settings(): LengthAwarePaginator
    {
        return AppSetting::query()->latest('updated_at')->paginate(15)->withQueryString()->through(function (AppSetting $setting): array {
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
        return AuditLog::query()->latest('id')->paginate(15)->withQueryString()->through(function (AuditLog $log): array {
            return [
                'actor' => $log->actor_type.':'.$log->actor_id,
                'action' => $log->action,
                'entity' => $log->auditable_type,
                'entity_id' => $log->auditable_id,
                'created_at' => $log->created_at?->format('Y-m-d H:i'),
            ];
        });
    }
}
