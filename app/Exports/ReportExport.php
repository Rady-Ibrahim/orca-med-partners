<?php

declare(strict_types=1);

namespace App\Exports;

use App\Actions\Admin\ReportDataAction;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

final class ReportExport implements FromArray, WithHeadings
{
    public function __construct(
        private ReportDataAction $reports,
        private string $report,
        private array $filters,
    ) {}

    public function headings(): array
    {
        return array_values($this->reports->columns($this->report));
    }

    public function array(): array
    {
        $rows = $this->reports->execute($this->report, $this->filters, false);
        $normalized = $this->reports->normalize($rows, $this->report);

        return $normalized->map(fn(array $row): array => array_values($row))->all();
    }
}
