<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\ParticipantNotificationService;
use App\Services\SecurityAuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Investment extends Model
{
    protected $table = 'investments';

    protected $fillable = [
        'participant_id',
        'amount',
        'invested_at',
        'status',
        'notes',
        'created_by_admin_id',
        'approved_by_admin_id',
        'approved_at',
        'approved_by_admin_id',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'invested_at' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (self $investment): void {
            app(SecurityAuditService::class)->log('investment_updated', $investment->created_by_admin_id ? Admin::query()->find($investment->created_by_admin_id) : null, 'investment', $investment->id, ['old' => $investment->getOriginal(), 'new' => $investment->only(['participant_id', 'amount', 'invested_at', 'status', 'approved_at'])]);
            if (! $investment->participant) {
                return;
            }

            $type = $investment->wasChanged('amount') ? 'investment_value_update' : 'investment_update';
            app(ParticipantNotificationService::class)->afterCommit(function () use ($investment, $type): void {
                app(ParticipantNotificationService::class)->createForParticipant($investment->participant, $type, 'تحديث الاستثمار', 'تم تحديث بيانات استثمارك.', null, ['investment_id' => $investment->id]);
            });
        });

        static::created(function (self $investment): void {
            app(SecurityAuditService::class)->log('investment_created', $investment->created_by_admin_id ? Admin::query()->find($investment->created_by_admin_id) : null, 'investment', $investment->id, ['new' => $investment->only(['participant_id', 'amount', 'invested_at', 'status'])]);
        });
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }
}
