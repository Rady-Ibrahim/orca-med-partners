<?php

declare(strict_types=1);

namespace App\Domain\Financial\Services;

use App\Domain\Financial\ValueObjects\FinancialRoundingService;

final class ProfitProjectionService
{
    public const MONTHLY_NET_RATE = '0.018';

    public const ANNUAL_NET_RATE = '0.216';

    public const PARTICIPANT_SHARE_RATE = '0.650';

    public const COMPANY_DEDUCTIONS_RATE = '0.350';

    private const MONTHS_PER_PERIOD = [
        'month' => 1,
        'quarter' => 3,
        'semi_annual' => 6,
        'annual' => 12,
        'years' => 12,
    ];

    private const SCALE = 10;

    public function __construct(
        private readonly FinancialRoundingService $rounding,
        private readonly RoiCalculatorService $roi,
    ) {}

    /**
     * Project the expected net profit for an invested amount over a period.
     *
     * Sub-annual periods (month, quarter, semi_annual) always use a simple
     * net rate (1.8% monthly / 21.6% yearly). Annual and multi-year periods
     * use the annual rate in simple mode, and when $isCompounded is true they
     * follow the compounding ladder of RoiCalculatorService.
     *
     * @return array{
     *     initial_amount: string,
     *     period_details: array{period_type: string, period_value: int, total_months: int, label: string},
     *     is_compounded: bool,
     *     calculation_mode: string,
     *     effective_net_rate: string,
     *     expected_net_profit: string,
     *     expected_total_balance: string,
     *     gross_breakdown_info: array<string, mixed>,
     *     disclaimer: string,
     * }
     */
    public function project(string $amount, string $periodType, int $periodValue, bool $isCompounded = false): array
    {
        $initial = $this->rounding->money($amount);
        $months = $periodValue * self::MONTHS_PER_PERIOD[$periodType];
        $yearlyPeriod = in_array($periodType, ['annual', 'years'], true);

        if ($yearlyPeriod && $isCompounded) {
            $simulated = $this->roi->simulate(
                $initial,
                $periodValue,
                self::ANNUAL_NET_RATE,
                RoiCalculatorService::DEFAULT_GROWTH_BONUSES,
                true,
            );
            $netProfit = $simulated['projected_profit'];
            $mode = 'compounded';
            $rate = self::ANNUAL_NET_RATE;
        } else {
            $netRate = $yearlyPeriod
                ? bcmul(self::ANNUAL_NET_RATE, (string) $periodValue, self::SCALE)
                : bcmul(self::MONTHLY_NET_RATE, (string) $months, self::SCALE);
            $netProfit = $this->rounding->money(bcmul($initial, $netRate, self::SCALE));
            $mode = 'simple';
            $rate = $netRate;
        }

        return [
            'initial_amount' => $initial,
            'period_details' => [
                'period_type' => $periodType,
                'period_value' => $periodValue,
                'total_months' => $months,
                'label' => $this->periodLabel($periodType, $periodValue),
            ],
            'is_compounded' => $isCompounded,
            'calculation_mode' => $mode,
            'effective_net_rate' => $this->rounding->rate($rate),
            'expected_net_profit' => $netProfit,
            'expected_total_balance' => $this->rounding->money(bcadd($initial, $netProfit, 2)),
            'gross_breakdown_info' => [
                'participant_share_rate' => $this->rounding->rate(self::PARTICIPANT_SHARE_RATE),
                'company_deductions_rate' => $this->rounding->rate(self::COMPANY_DEDUCTIONS_RATE),
                'deduction_breakdown' => [
                    'management_fee_rate' => $this->rounding->rate('0.25'),
                    'depreciation_fund_rate' => $this->rounding->rate('0.05'),
                    'growth_fund_rate' => $this->rounding->rate('0.025'),
                    'incentive_fund_rate' => $this->rounding->rate('0.025'),
                ],
                'net_monthly_rate' => $this->rounding->rate(self::MONTHLY_NET_RATE),
                'net_annual_rate' => $this->rounding->rate(self::ANNUAL_NET_RATE),
                'note' => 'صافي الربح هو 65% من إجمالي الربح بعد خصم 35% تحتفظ بها الشركة لصناديقها: الإدارة 25%، الإهلاك 5%، النمو 2.5%، والحافز 2.5%.',
            ],
            'disclaimer' => 'هذه توقعات استرشادية تعتمد على معدلات افتراضية ولا تمثل ضماناً للعائد الفعلي.',
        ];
    }

    private function periodLabel(string $periodType, int $value): string
    {
        $unit = match ($periodType) {
            'month' => $value === 1 ? 'شهر' : 'أشهر',
            'quarter' => $value === 1 ? 'ربع سنوي' : 'أرباع سنوية',
            'semi_annual' => $value === 1 ? 'نصف سنوي' : 'فترات نصف سنوية',
            'annual', 'years' => $value === 1 ? 'سنة' : 'سنوات',
            default => $periodType,
        };

        return sprintf('%d %s', $value, $unit);
    }
}
