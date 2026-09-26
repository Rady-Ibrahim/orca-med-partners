<?php

declare(strict_types=1);

use App\Actions\Admin\ReportDataAction;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Collection;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$action = app(ReportDataAction::class);
$year = $argv[1] ?? '2026';

$reports = $argv[2] ?? null;
$reports = $reports === null
    ? ['monthly-profits', 'annual-profits', 'settlements', 'due-paid', 'funds', 'fund-shares', 'distribution']
    : array_map('trim', explode(',', $reports));

$yearScoped = ['monthly-profits', 'annual-profits', 'settlements', 'due-paid', 'fund-shares', 'distribution', 'depreciation'];

$scalar = static function (mixed $value): string {
    if ($value === null) {
        return '—';
    }

    if (is_bool($value)) {
        return $value ? 'yes' : 'no';
    }

    if (is_array($value)) {
        foreach (['label', 'value', 'name', 'code'] as $key) {
            if (isset($value[$key]) && (is_scalar($value[$key]) || $value[$key] === null)) {
                return (string) $value[$key];
            }
        }

        return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    if (is_object($value)) {
        return method_exists($value, '__toString') ? (string) $value : get_class($value);
    }

    return (string) $value;
};

foreach ($reports as $report) {
    $filters = in_array($report, $yearScoped, true) ? ['year' => $year] : [];

    $raw = $action->execute($report, $filters, false);
    $rows = $action->normalize($raw instanceof Collection ? $raw : collect($raw->items()), $report);
    $totals = $action->totals($report, $filters);
    $labels = $action->columns($report);

    echo "\n".str_repeat('=', 110)."\n";
    echo 'REPORT: '.$action->title($report)."   ({$report})   filters=".json_encode($filters, JSON_UNESCAPED_UNICODE)."\n";
    echo str_repeat('=', 110)."\n";

    if ($rows->isEmpty()) {
        echo "(no rows)\n";

        continue;
    }

    $data = $rows->all();

    $matrix = [];

    foreach ($data as $row) {
        $flat = [];

        foreach ($labels as $key => $label) {
            $flat[$key] = $scalar(is_array($row) ? ($row[$key] ?? null) : ($row->{$key} ?? null));
        }

        $matrix[] = $flat;
    }

    if (trim((string) getenv('TOOL_REPORTS_DEBUG')) === '1') {
        fwrite(STDERR, 'DEBUG matrix0_period='.var_export($matrix[0]['period'] ?? '<<MISSING>>', true)."\n");
    }

    $widths = [];

    foreach ($labels as $key => $label) {
        $lengths = array_map(static fn (array $row) => mb_strlen($row[$key] ?? ''), $matrix);
        $widths[$key] = min(38, max(mb_strlen((string) $label), ...$lengths));
    }

    $headerLine = [];

    foreach ($labels as $key => $label) {
        $headerLine[] = $label.str_repeat(' ', max(0, $widths[$key] - mb_strlen((string) $label)));
    }

    echo implode(' | ', $headerLine), "\n";
    echo implode('-+-', array_map(static fn ($key) => str_repeat('-', $widths[$key]), array_keys($labels))), "\n";

    foreach ($matrix as $row) {
        $cells = [];

        foreach ($labels as $key => $label) {
            $cells[] = mb_str_pad($row[$key] ?? '', $widths[$key]);
        }

        echo implode(' | ', $cells), "\n";
    }

    if ($totals !== null) {
        $parts = [];

        foreach ($totals as $key => $value) {
            $text = $scalar($value);

            if ($text !== '—' && $text !== '0.00' && $text !== '') {
                $parts[] = ($labels[$key] ?? $key)."={$text}";
            }
        }

        if ($parts !== []) {
            echo str_repeat('-', 110), "\n";
            echo 'TOTALS: '.implode('  ', $parts), "\n";
        }
    }
}
