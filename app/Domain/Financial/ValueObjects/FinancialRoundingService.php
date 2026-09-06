<?php

declare(strict_types=1);

namespace App\Domain\Financial\ValueObjects;

use InvalidArgumentException;

final class FinancialRoundingService
{
    public function money(string|int $value): string
    {
        return $this->halfEven((string) $value, 2);
    }

    public function rate(string|int $value): string
    {
        return $this->halfEven((string) $value, 4);
    }

    public function halfEven(string $value, int $scale): string
    {
        if ($scale < 0 || ! preg_match('/^-?\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Invalid decimal value.');
        }

        $negative = str_starts_with($value, '-');
        $unsigned = ltrim($value, '-');
        [$integer, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $fraction = str_pad($fraction, $scale + 1, '0');
        $kept = substr($fraction, 0, $scale);
        $guard = (int) ($fraction[$scale] ?? '0');
        $tail = substr($fraction, $scale + 1);
        $lastKept = $scale > 0 ? (int) ($kept[$scale - 1] ?? '0') : (int) substr($integer, -1);
        $increment = $guard > 5 || ($guard === 5 && (trim($tail, '0') !== '' || $lastKept % 2 === 1));

        $scaled = $scale === 0 ? $integer : $integer . '.' . $kept;
        if ($increment) {
            $step = $scale === 0 ? '1' : '0.' . str_repeat('0', $scale - 1) . '1';
            $scaled = bcadd($scaled, $step, $scale);
        }

        if ($scale === 0) {
            return ($negative && $scaled !== '0' ? '-' : '') . $scaled;
        }

        [$resultInteger, $resultFraction] = array_pad(explode('.', $scaled, 2), 2, '');
        $result = $resultInteger . '.' . str_pad($resultFraction, $scale, '0');

        return $negative && $result !== '0.' . str_repeat('0', $scale) ? '-' . $result : $result;
    }
}
