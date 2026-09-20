<?php

declare(strict_types=1);

namespace App\Domain\Financial\Services;

use App\Domain\Financial\ValueObjects\FinancialRoundingService;
use InvalidArgumentException;

final class RoiCalculatorService
{
    public const DEFAULT_BASE_ANNUAL_RATE = '0.216';

    public const DEFAULT_GROWTH_BONUSES = ['0.005', '0.010', '0.0075', '0.005'];

    private const SCALE = 10;

    public function __construct(
        private readonly FinancialRoundingService $rounding,
    ) {}

    /**
     * Authoritative server-side annual ROI projection with a dynamic growth
     * rate bonus applied on top of a base net annual rate.
     *
     * baseAnnualRate is an annual decimal ratio (e.g. 0.216 = 21.6%/year). Each
     * growthBonuses entry is an annual decimal ratio (e.g. 0.005 = +0.5%)
     * added on top of the base rate for that year; the final bonus repeats for
     * every year beyond the ladder (year 4+ keeps using the last bonus).
     *
     * Compounding rule: the investor never withdraws profits. Beginning capital
     * of year t = closing capital of year t-1, so the profit of every year is
     * computed on the full accumulated balance (never just the original base).
     * When isCompounded is false the profit stays flat on the original base
     * capital while the schedule still tracks the cumulative projected balance.
     *
     * All math is performed with BCMath; every exposed monetary value is a
     * 2-decimal money string and every rate is a 4-decimal decimal string.
     *
     * @param  list<string>  $growthBonuses
     * @return array{base_capital: string, years: int, is_compounded: bool, base_annual_rate: string, growth_bonus: list<string>, projected_capital: string, projected_profit: string, average_annual_profit: string, schedule: list<array{year: int, expected_annual_rate: string, profit: string, capital: string}>}
     */
    public function simulate(
        string $baseCapital,
        int $years,
        string $baseAnnualRate = self::DEFAULT_BASE_ANNUAL_RATE,
        array $growthBonuses = self::DEFAULT_GROWTH_BONUSES,
        bool $isCompounded = true,
    ): array {
        $opening = $this->rounding->money($baseCapital);
        $baseRate = $this->rounding->rate($baseAnnualRate);
        $bonuses = $this->normalisedBonuses($growthBonuses, $years);

        $carry = $opening;
        $schedule = [];

        for ($year = 1; $year <= $years; $year++) {
            $effectiveRate = $this->rounding->rate(bcadd($baseRate, $bonuses[$year - 1], self::SCALE));
            $profitBase = $isCompounded ? $carry : $opening;
            $profit = $this->rounding->money(bcmul($profitBase, $effectiveRate, self::SCALE));
            $closing = $this->rounding->money(bcadd($carry, $profit, self::SCALE));

            $schedule[] = [
                'year' => $year,
                'expected_annual_rate' => $effectiveRate,
                'profit' => $profit,
                'capital' => $closing,
            ];

            $carry = $closing;
        }

        $projectedCapital = $this->rounding->money($carry);
        $projectedProfit = $this->rounding->money(bcsub($projectedCapital, $opening, 2));

        return [
            'base_capital' => $opening,
            'years' => $years,
            'is_compounded' => $isCompounded,
            'base_annual_rate' => $baseRate,
            'growth_bonus' => $bonuses,
            'projected_capital' => $projectedCapital,
            'projected_profit' => $projectedProfit,
            'average_annual_profit' => $this->rounding->money(bcdiv($projectedProfit, (string) $years, self::SCALE)),
            'schedule' => $schedule,
        ];
    }

    /**
     * @param  list<string>  $bonuses
     * @return list<string>
     */
    private function normalisedBonuses(array $bonuses, int $years): array
    {
        if ($bonuses === []) {
            throw new InvalidArgumentException('Growth bonus ladder cannot be empty.');
        }

        $normalised = array_map(fn (mixed $bonus): string => $this->rounding->rate((string) $bonus), $bonuses);

        if ($years <= count($normalised)) {
            return array_slice($normalised, 0, $years);
        }

        return array_merge($normalised, array_fill(0, $years - count($normalised), $normalised[array_key_last($normalised)]));
    }
}
