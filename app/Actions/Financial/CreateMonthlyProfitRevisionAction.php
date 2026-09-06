<?php

declare(strict_types=1);

namespace App\Actions\Financial;

use App\Models\Admin;
use App\Models\MonthlyProfit;
use App\Services\SecurityAuditService;
use Illuminate\Support\Facades\DB;

final class CreateMonthlyProfitRevisionAction
{
    public function __construct(
        private CreateMonthlyProfitAction $create,
        private SecurityAuditService $audit,
    ) {}

    public function execute(Admin $admin, MonthlyProfit $approvedProfit, string|int $grossProfit): MonthlyProfit
    {
        return DB::transaction(function () use ($admin, $approvedProfit, $grossProfit) {
            $approvedProfit = MonthlyProfit::query()->lockForUpdate()->findOrFail($approvedProfit->id);
            abort_if($approvedProfit->status !== 'approved', 422, 'Only approved monthly profit records can be revised.');

            $revision = $this->create->execute(
                $admin,
                $approvedProfit->capitalSnapshot,
                $approvedProfit->distributionRule,
                $grossProfit,
                (int) $approvedProfit->year,
                (int) $approvedProfit->month,
                $approvedProfit->id,
                $approvedProfit->distribution_rule_snapshot,
            );

            $approvedProfit->status = 'superseded';
            $approvedProfit->save();

            $this->audit->log('monthly_profit_revision_created', $admin, 'monthly_profit', $revision->id, [
                'parent_id' => $approvedProfit->id,
                'version' => $revision->version,
            ]);

            return $revision;
        });
    }
}
