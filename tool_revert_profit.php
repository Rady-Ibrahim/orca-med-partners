<?php

declare(strict_types=1);

use App\Domain\Financial\Services\FundBalanceService;
use App\Models\DepreciationNote;
use App\Models\Fund;
use App\Models\FundTransaction;
use App\Models\MonthlyProfit;
use App\Models\ParticipantFundAllocation;
use App\Models\ParticipantProfitAllocation;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) DB::connection()->getDatabaseName();
$profitId = (int) ($argv[1] ?? 0);
$force = in_array('--force', $argv, true);

if ($database !== 'orca-med-partners') {
    fwrite(STDERR, "REFUSING TO REVERT: active database is '{$database}', expected 'orca-med-partners'.\n");
    exit(1);
}

$profit = MonthlyProfit::query()->find($profitId);

if ($profit === null) {
    fwrite(STDERR, "NOTHING TO DO: monthly_profit {$profitId} does not exist.\n");
    exit(0);
}

$counts = [
    'participant_profit_allocations' => ParticipantProfitAllocation::query()->where('monthly_profit_id', $profitId)->count(),
    'participant_fund_allocations' => ParticipantFundAllocation::query()->where('monthly_profit_id', $profitId)->count(),
    'depreciation_notes' => DepreciationNote::query()->where('monthly_profit_id', $profitId)->count(),
    'fund_transactions' => FundTransaction::query()->where('monthly_profit_id', $profitId)->count(),
    'notifications' => DB::table('notifications')->where('metadata', 'like', '%monthly_profit_id":'.$profitId.'%')->count(),
];

$children = MonthlyProfit::query()->where('parent_id', $profitId)->count();

echo "REVERT TARGET monthly_profit id={$profit->id} year={$profit->year} month={$profit->month} version={$profit->version} status={$profit->status} gross={$profit->gross_profit}\n";

foreach ($counts as $table => $count) {
    echo "  {$table}: {$count}\n";
}

echo "  child_profits: {$children}\n";

if ($children > 0) {
    fwrite(STDERR, "REFUSING TO REVERT: this profit has {$children} dependent child profit(s).\n");
    exit(1);
}

if (! $force) {
    echo "\nDRY RUN. Re-run with --force to execute.\n";
    exit(0);
}

DB::transaction(function () use ($profitId): void {
    DB::table('notifications')->where('metadata', 'like', '%monthly_profit_id":'.$profitId.'%')->delete();
    DepreciationNote::query()->where('monthly_profit_id', $profitId)->delete();
    ParticipantFundAllocation::query()->where('monthly_profit_id', $profitId)->delete();
    ParticipantProfitAllocation::query()->where('monthly_profit_id', $profitId)->delete();
    FundTransaction::query()->where('monthly_profit_id', $profitId)->delete();
    MonthlyProfit::query()->whereKey($profitId)->delete();
});

$service = app(FundBalanceService::class);

foreach (Fund::query()->orderBy('id')->get() as $fund) {
    $service->recalculateRunningBalances($fund);
}

echo "\nREVERTED. fund balances recalculated from transactions.\n";

Artisan::call('finance:reconcile', ['--year' => $profit->year, '--force' => true]);
echo Artisan::output();
