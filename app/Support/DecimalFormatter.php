<?php

declare(strict_types=1);

namespace App\Support;

final class DecimalFormatter
{
    public static function money(string|int|null $value, int $scale = 2): string
    {
        $value = (string) ($value ?? '0');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $negative = str_starts_with($whole, '-');
        $whole = ltrim($whole, '-');
        $fraction = str_pad(substr($fraction, 0, $scale), $scale, '0');
        $whole = number_format((int) ($whole ?: '0'), 0, '.', ',');

        return ($negative ? '-' : '') . $whole . ($scale > 0 ? '.' . $fraction : '');
    }

    public static function percent(string|int|null $rate): string
    {
        return self::money(bcmul((string) ($rate ?? '0'), '100', 2), 2) . '%';
    }

    public static function ratioPercent(string|int|null $value, string|int|null $total): string
    {
        if (bccomp((string) ($total ?? '0'), '0', 2) === 0) {
            return '0';
        }

        $percent = bcmul(bcdiv((string) ($value ?? '0'), (string) $total, 6), '100', 2);

        return bccomp($percent, '100', 2) > 0 ? '100' : $percent;
    }
}
