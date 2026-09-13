<?php

declare(strict_types=1);

namespace App\Domain\Financial\Services;

use App\Domain\Financial\ValueObjects\FinancialRoundingService;

final class RoiCalculatorService
{
    private const SCALE = 10;

    public function __construct(
        private readonly FinancialRoundingService $rounding,
    ) {}

    /**
     * Authoritative server-side compound-interest projection.
     *
     * rate is a decimal ratio (e.g. 0.15 = 15%/year). All math is performed
     * with BCMath; every exposed monetary value is a 2-decimal money string.
     *
     * @return array{base_capital: string, years: int, expected_annual_rate: string, projected_capital: string, projected_profit: string, schedule: array<int, array{year: int, opening_capital: string, profit: string, closing_capital: string}>}
     */
    public function simulate(string $baseCapital, int $years, string $annualRate): array
    {
        $carry = $this->rounding->money($baseCapital);
        $rate = $this->rounding->rate($annualRate);
        $schedule = [];

        for ($year = 1; $year <= $years; $year++) {
            $profit = bcmul($carry, $rate, self::SCALE);
            $closing = bcadd($carry, $profit, self::SCALE);

            $schedule[] = [
                'year' => $year,
                'opening_capital' => $this->rounding->money($carry),
                'profit' => $this->rounding->money($profit),
                'closing_capital' => $this->rounding->money($closing),
            ];

            $carry = $closing;
        }

        $projectedCapital = $this->rounding->money($carry);
        $opening = $this->rounding->money($baseCapital);

        return [
            'base_capital' => $opening,
            'years' => $years,
            'expected_annual_rate' => $rate,
            'projected_capital' => $projectedCapital,
            'projected_profit' => $this->rounding->money(bcsub($projectedCapital, $opening, 2)),
            'schedule' => $schedule,
        ];
    }
}