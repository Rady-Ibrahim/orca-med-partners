<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\FundTransaction;
use App\Models\Investment;

echo "== INVESTMENTS ==\n";
foreach (Investment::query()->orderBy('id')->get() as $i) {
    echo "id={$i->id} status={$i->status} amount={$i->amount} participant={$i->participant_id} at={$i->invested_at}\n";
}

echo "\n== FUND TRANSACTIONS ==\n";
foreach (FundTransaction::query()->orderBy('id')->get() as $t) {
    echo "id={$t->id} fund={$t->fund_id} type={$t->transaction_type} amount={$t->amount} resulting={$t->resulting_balance} date={$t->transaction_date} profit=" . var_export($t->monthly_profit_id, true) . " ref=" . var_export($t->reference, true) . " desc=" . var_export($t->description, true) . " notes=" . var_export($t->notes, true) . "\n";
}

echo "\n== FUNDS ==\n";
foreach (\App\Models\Fund::query()->orderBy('id')->get() as $f) {
    echo "id={$f->id} code={$f->code} name={$f->name} balance={$f->current_balance} status={$f->status}\n";
}