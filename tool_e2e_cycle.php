<?php

/**
 * End-to-end verification of the full financial cycle on an isolated database.
 *
 * Mirrors the admin workflow: capital page -> monthly profits -> approval ->
 * annual settlement, then prints the numbers that step 4 of the E2E plan
 * asserts. Intended to run against a throwaway database, for example:
 *
 *   set DB_CONNECTION=sqlite
 *   set DB_DATABASE=<path>\e2e_orca.sqlite
 *   php artisan migrate:fresh --seed
 *   php tool_e2e_cycle.php
 *   php tool_query.php "<sql>"
 *
 * It never runs against the development MySQL copy by accident because the
 * connection must be overridden explicitly on the command line.
 */

declare(strict_types=1);

use App\Actions\Financial\ApproveMonthlyProfitAction;
use App\Actions\Financial\CreateMonthlyProfitAction;
use App\Actions\Settlements\CreateAnnualSettlementAction;
use App\Models\Admin;
use App\Models\CapitalSnapshot;
use App\Models\DistributionRule;
use App\Models\Fund;
use App\Models\MonthlyProfit;
use App\Models\Participant;
use App\Models\Settlement;
use App\Models\SettlementItem;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = config('database.default');

if ($connection !== 'sqlite') {
    fwrite(STDERR, "REFUSING TO RUN: active connection is '{$connection}'. Point DB_CONNECTION=sqlite and DB_DATABASE at a throwaway file first.\n");
    exit(1);
}

$database = (string) config('database.connections.sqlite.database');

if (str_contains($database, 'orca-med-partners') || ! str_ends_with($database, '.sqlite')) {
    fwrite(STDERR, "REFUSING TO RUN: '{$database}' does not look like a throwaway sqlite file.\n");
    exit(1);
}

function heading(string $title): void
{
    echo PHP_EOL, $title, PHP_EOL, str_repeat('-', mb_strlen($title)), PHP_EOL;
}

$YEAR = 2026;
$MONTHS = [1, 2, 3];
$GROSS_PER_MONTH = '1000000.00';
$CAPITAL = [
    ['username' => 'ahmed', 'first_name' => 'أحمد', 'last_name' => 'علي', 'capital' => '5000000.00', 'expected_ratio' => '0.5000'],
    ['username' => 'mustafa', 'first_name' => 'مصطفى', 'last_name' => 'سعيد', 'capital' => '3000000.00', 'expected_ratio' => '0.3000'],
    ['username' => 'mohamed', 'first_name' => 'محمد', 'last_name' => 'عمر', 'capital' => '2000000.00', 'expected_ratio' => '0.2000'],
];
$EXPECTED = [
    'management_fund' => '750000.00',
    'depreciation_fund' => '150000.00',
    'growth_fund' => '75000.00',
    'incentive_fund' => '75000.00',
    'participant_pool' => '1950000.00',
];

heading('0) Environment');
printf("connection .. %s\n", $connection);
printf("database .... %s\n", $database);

$existing = [
    'monthly_profits' => MonthlyProfit::query()->count(),
    'settlements' => Settlement::query()->count(),
    'capital_snapshots' => CapitalSnapshot::query()->count(),
];

if (array_sum($existing) > 0) {
    fwrite(STDERR, 'REFUSING TO RUN: this database already holds financial data ('.json_encode($existing).").\nRe-run `php artisan migrate:fresh --seed` first, otherwise a second pass would book revision version 2.\n");
    exit(1);
}

$admin = Admin::query()->where('username', 'superadmin')->firstOrFail();

heading('1) Participants');
$participants = [];

foreach ($CAPITAL as $row) {
    $participant = Participant::query()->updateOrCreate(
        ['username' => $row['username']],
        [
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'code' => 'E2E-'.strtoupper($row['username']),
            'password' => 'secret123',
            'status' => 'active',
            'created_by_admin_id' => $admin->id,
        ],
    );

    $participants[] = ['model' => $participant, 'meta' => $row];

    printf(
        "%-9s capital %15s  expected ratio %s\n",
        $row['username'],
        number_format((float) $row['capital'], 2),
        $row['expected_ratio'],
    );
}

$capitalTotal = '0.00';
foreach ($CAPITAL as $row) {
    $capitalTotal = bcadd($capitalTotal, $row['capital'], 2);
}
printf("%-9s capital %15s\n", 'TOTAL', number_format((float) $capitalTotal, 2));

heading('2) System funds');
foreach (['management_fund', 'depreciation_fund', 'growth_fund', 'incentive_fund'] as $code) {
    $fund = Fund::query()->firstOrCreate(
        ['code' => $code],
        ['name' => ucwords(str_replace('_', ' ', $code)), 'current_balance' => '0.00', 'status' => 'active', 'created_by_admin_id' => $admin->id],
    );

    printf("%-20s id %-4d balance %14s\n", $code, $fund->id, number_format((float) $fund->current_balance, 2));
}

heading('3) Distribution rule (25% / 5% / 2.5% / 2.5% / 65%)');
$rule = DistributionRule::query()->firstOrCreate(
    ['effective_from' => sprintf('%d-01-01', $YEAR)],
    [
        'management_fee_rate' => '0.2500',
        'depreciation_fund_rate' => '0.0500',
        'growth_fund_rate' => '0.0250',
        'incentive_fund_rate' => '0.0250',
        'distributed_share_rate' => '0.6500',
        'status' => 'active',
        'is_default' => true,
        'created_by_admin_id' => $admin->id,
    ],
);
printf("rule id %d from %s\n", $rule->id, $rule->effective_from);

heading('4) Capital snapshot via the capital page path (syncItems)');
$snapshot = CapitalSnapshot::query()->create([
    'snapshot_date' => sprintf('%d-01-31', $YEAR),
    'year' => $YEAR,
    'month' => 1,
    'total_capital' => '0.00',
    'status' => 'final',
    'created_by_admin_id' => $admin->id,
]);

$snapshot->syncItems(
    array_map(
        static fn (array $entry): array => [
            'participant_id' => $entry['model']->id,
            'capital' => $entry['meta']['capital'],
        ],
        $participants,
    ),
    $admin,
);

$snapshot->refresh();
printf("snapshot id %d  total %s\n", $snapshot->id, number_format((float) $snapshot->total_capital, 2));

foreach ($snapshot->items()->orderBy('id')->get() as $item) {
    printf(
        "  %-9s capital %15s  ratio %s\n",
        $item->participant->username,
        number_format((float) $item->participant_capital_snapshot, 2),
        (string) $item->participant_ratio_snapshot,
    );
}

$ratioSum = (string) $snapshot->items()->get()->reduce(
    static fn (string $carry, $item): string => bcadd($carry, (string) $item->participant_ratio_snapshot, 4),
    '0.0000',
);
printf("ratio sum %s\n", $ratioSum);

// Explicit second pass, proving recalculateRatios() is idempotent.
$repair = $snapshot->fresh()->recalculateRatios();
printf("recalculateRatios() changed=%s total=%s\n", $repair['changed'] ? 'yes' : 'no', $repair['total_capital']);

heading('5) Monthly profits (create + approve)');
$create = app(CreateMonthlyProfitAction::class);
$approve = app(ApproveMonthlyProfitAction::class);

foreach ($MONTHS as $month) {
    $profit = $create->execute($admin, $snapshot, $rule, $GROSS_PER_MONTH, $YEAR, $month);
    $approved = $approve->execute($admin, $profit);

    $allocations = $approved->allocations()->orderBy('participant_id')->get();
    $poolSum = (string) $allocations->reduce(
        static fn (string $carry, $allocation): string => bcadd($carry, (string) $allocation->amount, 2),
        '0.00',
    );

    printf(
        "month %d  gross %12s  mgmt %10s  depr %9s  growth %8s  incent %8s  pool %11s  (allocations %s)  status %s\n",
        $month,
        number_format((float) $approved->gross_profit, 2),
        number_format((float) $approved->management_amount, 2),
        number_format((float) $approved->depreciation_amount, 2),
        number_format((float) $approved->growth_amount, 2),
        number_format((float) $approved->incentive_amount, 2),
        number_format((float) $approved->distributed_amount, 2),
        $poolSum,
        $approved->status,
    );
}

heading('6) Cumulative fund balances');
$fundFailures = [];

foreach (['management_fund', 'depreciation_fund', 'growth_fund', 'incentive_fund'] as $code) {
    $fund = Fund::query()->where('code', $code)->firstOrFail();
    $balance = (string) $fund->current_balance;
    $expected = $EXPECTED[$code];
    $ok = $balance === $expected;

    if (! $ok) {
        $fundFailures[] = $code;
    }

    printf(
        "[%s] %-20s balance %14s  expected %14s\n",
        $ok ? 'PASS' : 'FAIL',
        $code,
        number_format((float) $balance, 2),
        number_format((float) $expected, 2),
    );
}

heading('7) Annual settlement');
/** @var Settlement $settlement */
$settlement = app(CreateAnnualSettlementAction::class)->execute($admin, $YEAR);

printf("settlement id %d year %d v%d status %s\n", $settlement->id, $settlement->year, $settlement->version, $settlement->status);
printf(
    "participant_profit_share %14s  amount_due %14s\n",
    number_format((float) $settlement->participant_profit_share, 2),
    number_format((float) $settlement->amount_due, 2),
);

$items = SettlementItem::query()
    ->where('settlement_id', $settlement->id)
    ->orderBy('id')
    ->get();

$itemsSum = '0.00';
foreach ($items as $item) {
    $itemsSum = bcadd($itemsSum, (string) $item->net_payable, 2);
    printf(
        "  %-9s net_payable %13s\n",
        $item->participant->username,
        number_format((float) $item->net_payable, 2),
    );
}
printf("%-9s %26s\n", 'ITEMS SUM', number_format((float) $itemsSum, 2));

heading('8) Assertions');
$failures = [];

$ratioOk = $ratioSum === '1.0000';
printf("[%s] snapshot ratio sum = 1.0000 (got %s)\n", $ratioOk ? 'PASS' : 'FAIL', $ratioSum);
$failures[] = $ratioOk ?: 'ratio sum';

$headerOk = bccomp((string) $snapshot->total_capital, $capitalTotal, 2) === 0;
printf("[%s] snapshot total equals sum of item capitals (%s)\n", $headerOk ? 'PASS' : 'FAIL', $snapshot->total_capital);
$failures[] = $headerOk ?: 'header total';

foreach ($CAPITAL as $index => $row) {
    $item = $snapshot->items()->where('participant_id', $participants[$index]['model']->id)->firstOrFail();
    $ok = bccomp((string) $item->participant_ratio_snapshot, $row['expected_ratio'], 4) === 0;
    printf("[%s] %-9s ratio %s (expected %s)\n", $ok ? 'PASS' : 'FAIL', $row['username'], (string) $item->participant_ratio_snapshot, $row['expected_ratio']);
    $failures[] = $ok ?: "ratio {$row['username']}";
}

foreach ($fundFailures as $code) {
    $failures[] = "fund {$code}";
}

$settlementOk = bccomp((string) $settlement->participant_profit_share, $EXPECTED['participant_pool'], 2) === 0;
printf("[%s] settlement participant_profit_share = %s\n", $settlementOk ? 'PASS' : 'FAIL', number_format((float) $settlement->participant_profit_share, 2));
$failures[] = $settlementOk ?: 'settlement pool';

$itemsOk = bccomp($itemsSum, (string) $settlement->participant_profit_share, 2) === 0;
printf("[%s] settlement items sum matches header (%s)\n", $itemsOk ? 'PASS' : 'FAIL', number_format((float) $itemsSum, 2));
$failures[] = $itemsOk ?: 'settlement items';

$dueOk = bccomp((string) $settlement->amount_due, (string) $settlement->participant_profit_share, 2) === 0;
printf("[%s] amount_due equals participant_profit_share\n", $dueOk ? 'PASS' : 'FAIL');
$failures[] = $dueOk ?: 'amount due';

$expectedShare = ['ahmed' => '975000.00', 'mustafa' => '585000.00', 'mohamed' => '390000.00'];
foreach ($expectedShare as $username => $expected) {
    $item = $items->firstWhere('participant.username', $username);
    $actual = $item ? (string) $item->net_payable : 'missing';
    $ok = $item !== null && bccomp($actual, $expected, 2) === 0;
    printf("[%s] %-9s settlement %14s (expected %14s)\n", $ok ? 'PASS' : 'FAIL', $username, number_format((float) $actual, 2), number_format((float) $expected, 2));
    $failures[] = $ok ?: "settlement share {$username}";
}

$profitOk = MonthlyProfit::query()->where('year', $YEAR)->where('status', 'approved')->count() === count($MONTHS);
printf("[%s] three approved monthly profits exist\n", $profitOk ? 'PASS' : 'FAIL');
$failures[] = $profitOk ?: 'approved profits';

$problems = array_values(array_filter($failures, static fn ($value): bool => $value === false));

echo PHP_EOL;

if ($problems === []) {
    echo 'RESULT: all checks passed.'.PHP_EOL;

    exit(0);
}

echo 'RESULT: '.count($problems).' check(s) failed: '.implode(', ', array_map('strval', $problems)).PHP_EOL;

exit(1);
