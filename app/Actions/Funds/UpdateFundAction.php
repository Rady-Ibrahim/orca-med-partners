<?php

declare(strict_types=1);

namespace App\Actions\Funds;

use App\Models\Admin;
use App\Models\Fund;
use App\Services\SecurityAuditService;
use Illuminate\Support\Facades\DB;

final class UpdateFundAction
{
    public function __construct(private SecurityAuditService $audit)
    {
    }

    /** @param array{code?:string,name?:string,status?:string,description?:string|null} $attributes */
    public function execute(Admin $admin, Fund $fund, array $attributes): Fund
    {
        return DB::transaction(function () use ($admin, $fund, $attributes) {
            $lockedFund = Fund::query()->lockForUpdate()->findOrFail($fund->id);
            $old = $lockedFund->only(['code', 'name', 'status', 'description', 'current_balance']);
            $lockedFund->fill($attributes);
            $lockedFund->save();

            $this->audit->log('fund_updated', $admin, 'fund', $lockedFund->id, [
                'old' => $old,
                'new' => $lockedFund->only(['code', 'name', 'status', 'description', 'current_balance']),
            ]);

            return $lockedFund->fresh();
        });
    }
}