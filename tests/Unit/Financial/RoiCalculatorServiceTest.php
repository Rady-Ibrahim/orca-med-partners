<?php

declare(strict_types=1);

namespace Tests\Unit\Financial;

use App\Domain\Financial\Services\RoiCalculatorService;
use App\Domain\Financial\ValueObjects\FinancialRoundingService;
use PHPUnit\Framework\TestCase;

final class RoiCalculatorServiceTest extends TestCase
{
    public function test_compound_interest_projection_matches_bcmath_reference(): void
    {
        $result = (new RoiCalculatorService(new FinancialRoundingService()))->simulate('1000.00', 3, '0.10');

        self::assertSame('1000.00', $result['base_capital']);
        self::assertSame(3, $result['months']);
        self::assertSame('0.1000', $result['expected_monthly_rate']);
        self::assertSame('1331.00', $result['projected_capital']);
        self::assertSame('331.00', $result['projected_profit']);
        self::assertSame('110.33', $result['average_monthly_profit']);

        self::assertSame([
            ['month' => 1, 'capital' => '1100.00', 'profit' => '100.00'],
            ['month' => 2, 'capital' => '1210.00', 'profit' => '110.00'],
            ['month' => 3, 'capital' => '1331.00', 'profit' => '121.00'],
        ], $result['schedule']);
    }

    public function test_non_integer_rate_and_large_capital_stay_decimal_exact(): void
    {
        $result = (new RoiCalculatorService(new FinancialRoundingService()))->simulate('1500000.00', 2, '0.155');

        self::assertSame('2001037.50', $result['projected_capital']);
        self::assertSame('501037.50', $result['projected_profit']);
        self::assertSame('250518.75', $result['average_monthly_profit']);
    }

    public function test_loss_rate_produces_negative_profit_but_never_negative_capital_logic(): void
    {
        $result = (new RoiCalculatorService(new FinancialRoundingService()))->simulate('100.00', 1, '-0.20');

        self::assertSame('80.00', $result['projected_capital']);
        self::assertSame('-20.00', $result['projected_profit']);
        self::assertSame('-20.00', $result['average_monthly_profit']);
    }

    public function test_single_month_matches_flat_interest(): void
    {
        $result = (new RoiCalculatorService(new FinancialRoundingService()))->simulate('500000.00', 1, '0.12');

        self::assertSame('560000.00', $result['projected_capital']);
        self::assertSame('60000.00', $result['projected_profit']);
        self::assertSame('60000.00', $result['average_monthly_profit']);
    }
}