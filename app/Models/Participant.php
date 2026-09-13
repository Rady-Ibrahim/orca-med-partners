<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Participant extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'participants';

    protected $fillable = [
        'first_name',
        'last_name',
        'username',
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

    public function hasPermission(string $permission): bool
    {
        $permissions = $this->permissions ?? [];

        return in_array($permission, $permissions, true);
    }
}
