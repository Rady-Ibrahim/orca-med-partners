<?php

declare(strict_types=1);

namespace App\Actions\Investment;

use App\Models\Admin;
use App\Models\Investment;
use Illuminate\Support\Facades\DB;

final class ApproveInvestmentAction
{
    public function execute(Admin $admin, Investment $investment): Investment
    {
        return DB::transaction(function () use ($admin, $investment) {
            $investment = Investment::query()->lockForUpdate()->findOrFail($investment->id);
            $investment->forceFill([
                'status' => 'approved',
                'approved_by_admin_id' => $admin->id,
                'approved_at' => now(),
            ])->save();

            return $investment->fresh();
        });
    }
}
