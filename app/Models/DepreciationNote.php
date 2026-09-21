<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\ParticipantNotificationService;
use App\Services\SecurityAuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepreciationNote extends Model
{
    protected $table = 'depreciation_notes';

    protected $fillable = [
        'participant_id',
        'fund_id',
        'monthly_profit_id',
        'amount',
        'rate',
        'transaction_date',
        'year',
        'month',
        'description',
        'admin_note',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'rate' => 'decimal:4',
            'transaction_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $note): void {
            app(SecurityAuditService::class)->log('depreciation_created', $note->created_by_admin_id ? Admin::query()->find($note->created_by_admin_id) : null, 'depreciation_note', $note->id, ['new' => $note->only(['amount', 'rate', 'transaction_date', 'year', 'month', 'description', 'admin_note'])]);
            if ($note->participant) {
                app(ParticipantNotificationService::class)->afterCommit(function () use ($note): void {
                    app(ParticipantNotificationService::class)->createForParticipant($note->participant, 'depreciation_update', 'تحديث ملاحظة إهلاك', 'تم تحديث ملاحظة إهلاك مرتبطة بحسابك.', null, ['depreciation_note_id' => $note->id]);
                });
            }
        });

        static::updated(function (self $note): void {
            app(SecurityAuditService::class)->log('depreciation_updated', $note->created_by_admin_id ? Admin::query()->find($note->created_by_admin_id) : null, 'depreciation_note', $note->id, ['old' => $note->getOriginal(), 'new' => $note->only(['amount', 'rate', 'transaction_date', 'year', 'month', 'description', 'admin_note'])]);
            if ($note->participant) {
                app(ParticipantNotificationService::class)->afterCommit(function () use ($note): void {
                    app(ParticipantNotificationService::class)->createForParticipant($note->participant, 'depreciation_update', 'تحديث ملاحظة إهلاك', 'تم تحديث ملاحظة إهلاك مرتبطة بحسابك.', null, ['depreciation_note_id' => $note->id]);
                });
            }
        });
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function monthlyProfit(): BelongsTo
    {
        return $this->belongsTo(MonthlyProfit::class);
    }
}
