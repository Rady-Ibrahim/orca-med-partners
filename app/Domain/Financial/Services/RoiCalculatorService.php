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
     * rate is a monthly decimal ratio (e.g. 0.015 = 1.5%/month). All math is
     * performed with BCMath; every exposed monetary value is a 2-decimal money
     * string. The schedule is per-month and 'capital' is the closing capital
     * at the end of that month.
     *
     * @return array{base_capital: string, months: int, expected_monthly_rate: string, projected_capital: string, projected_profit: string, average_monthly_profit: string, schedule: array<int, array{month: int, capital: string, profit: string}>}
     */
    public function simulate(string $baseCapital, int $months, string $monthlyRate): array
    {
        $opening = $this->rounding->money($baseCapital);
        $rate = $this->rounding->rate($monthlyRate);
        $carry = $opening;
        $schedule = [];

        for ($month = 1; $month <= $months; $month++) {
            $profit = bcmul($carry, $rate, self::SCALE);
            $closing = bcadd($carry, $profit, self::SCALE);

            $schedule[] = [
                'month' => $month,
                'capital' => $this->rounding->money($closing),
                'profit' => $this->rounding->money($profit),
            ];

            $carry = $closing;
        }

        $projectedCapital = $this->rounding->money($carry);
        $projectedProfit = $this->rounding->money(bcsub($projectedCapital, $opening, 2));

        return [
            'base_capital' => $opening,
            'months' => $months,
            'expected_monthly_rate' => $rate,
            'projected_capital' => $projectedCapital,
            'projected_profit' => $projectedProfit,
            'average_monthly_profit' => $this->rounding->money(bcdiv($projectedProfit, (string) $months, self::SCALE)),
            'schedule' => $schedule,
        ];
    }
}