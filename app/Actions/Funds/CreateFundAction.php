<?php

declare(strict_types=1);

namespace App\Actions\Funds;

use App\Models\Admin;
use App\Models\Fund;
use App\Services\SecurityAuditService;
use Illuminate\Support\Facades\DB;

final class CreateFundAction
{
    public function __construct(private SecurityAuditService $audit)
    {
    }

    /** @param array{code:string,name:string,status?:string,description?:string|null} $attributes */
    public function execute(Admin $admin, array $attributes): Fund
    {
        return DB::transaction(function () use ($admin, $attributes) {
            $fund = Fund::query()->create([
                'code' => $attributes['code'],
                'name' => $attributes['name'],
                'status' => $attributes['status'] ?? 'active',
                'description' => $attributes['description'] ?? null,
                'created_by_admin_id' => $admin->id,
                'current_balance' => '0.00',
            ]);

            $this->audit->log('fund_created', $admin, 'fund', $fund->id, [
                'new' => $fund->only(['code', 'name', 'status', 'description', 'current_balance']),
            ]);

            return $fund->fresh();
        });
    }
}