<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Participant extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'participants';

    protected $fillable = [
        'first_name',
        'last_name',
        'username',
        'code',
        'email',
        'password',
        'status',
        'two_factor_enabled',
        'two_factor_enabled_at',
        'created_by_admin_id',
        'role',
        'permissions',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'permissions' => 'array',
            'two_factor_enabled' => 'boolean',
            'two_factor_enabled_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return strtolower((string) $this->status) === 'active';
    }

    public function investments(): HasMany
    {
        return $this->hasMany(Investment::class);
    }

    public function capitalSnapshotItems(): HasMany
    {
        return $this->hasMany(CapitalSnapshotItem::class);
    }

    public function profitAllocations(): HasMany
    {
        return $this->hasMany(ParticipantProfitAllocation::class);
    }

    public function fundAllocations(): HasMany
    {
        return $this->hasMany(ParticipantFundAllocation::class);
    }

    public function depreciationNotes(): HasMany
    {
        return $this->hasMany(DepreciationNote::class);
    }

    public function settlementItems(): HasMany
    {
        return $this->hasMany(SettlementItem::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function settlements(): HasManyThrough
    {
        return $this->hasManyThrough(
            Settlement::class,
            SettlementItem::class,
            'participant_id',
            'id',
            'id',
            'settlement_id'
        );
    }

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];

        return in_array($permission, $permissions, true);
    }
}
