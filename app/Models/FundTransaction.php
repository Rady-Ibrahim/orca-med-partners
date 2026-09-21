<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundTransaction extends Model
{
    protected $table = 'fund_transactions';

    protected $fillable = [
        'fund_id',
        'monthly_profit_id',
        'transaction_type',
        'transaction_date',
        'amount',
        'resulting_balance',
        'reference',
        'description',
        'notes',
        'created_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'resulting_balance' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    public function monthlyProfit(): BelongsTo
    {
        return $this->belongsTo(MonthlyProfit::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }
}
