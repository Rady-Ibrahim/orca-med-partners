<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

final class ParticipantReportExport implements FromCollection, WithHeadings
{
    public function __construct(
        private readonly Collection $rows,
        private readonly array $headings,
    ) {}

    public function headings(): array
    {
        return array_values($this->headings);
    }

    public function collection(): Collection
    {
        return $this->rows->map(function (array $row): array {
            return array_map(
                static fn($value) => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value,
                array_values($row)
            );
        });
    }
}