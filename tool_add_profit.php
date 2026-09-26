<?php

declare(strict_types=1);

use App\Actions\Financial\ApproveMonthlyProfitAction;
use App\Actions\Financial\CreateMonthlyProfitAction;
use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\DistributionRule;
use App\Models\MonthlyProfit;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$year = (int) ($argv[1] ?? 2026);
$month = (int) ($argv[2] ?? 8);
$gross = (string) ($argv[3] ?? '3000000.00');
$approve = ! in_array('--draft', $argv, true);

$existing = MonthlyProfit::query()
    ->where('year', $year)
    ->where('month', $month)
    ->count();

if ($existing > 0) {
    fwrite(STDERR, "REFUSING TO RUN: {$year}-{$month} already has {$existing} profit record(s). Use a different month.\n");
    exit(1);
}

$admin = Admin::query()->orderBy('id')->firstOrFail();
$snapshot = CapitalSnapshot::query()->where('status', 'final')->orderByDesc('id')->firstOrFail();
$rule = DistributionRule::query()->where('is_default', true)->orderBy('id')->firstOrFail();

$profit = app(CreateMonthlyProfitAction::class)->execute($admin, $snapshot, $rule, $gross, $year, $month);

if ($approve) {
    $profit = app(ApproveMonthlyProfitAction::class)->execute($admin, $profit);
}

$profit->refresh();

echo "CREATED monthly_profit id={$profit->id} year={$profit->year} month={$profit->month} version={$profit->version} status={$profit->status}\n";
echo "  snapshot_id={$profit->capital_snapshot_id} rule_id={$profit->distribution_rule_id}\n";
echo "  gross={$profit->gross_profit} management={$profit->management_amount} depreciation={$profit->depreciation_amount} growth={$profit->growth_amount} incentive={$profit->incentive_amount}\n";
echo "  distributed={$profit->distributed_amount} rounding_delta={$profit->rounding_delta_adjustment}\n";
echo "REVERT WITH: php tool_revert_profit.php {$profit->id} --force\n";

if ($approve) {
    Artisan::call('finance:reconcile', ['--year' => $year, '--force' => true]);
    echo Artisan::output();
}
