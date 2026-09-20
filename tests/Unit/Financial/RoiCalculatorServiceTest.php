<?php

declare(strict_types=1);

namespace Tests\Unit\Financial;

use App\Domain\Financial\Services\RoiCalculatorService;
use App\Domain\Financial\ValueObjects\FinancialRoundingService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RoiCalculatorServiceTest extends TestCase
{
    public function test_three_year_compounded_projection_matches_specification_example(): void
    {
        $result = (new RoiCalculatorService(new FinancialRoundingService))
            ->simulate('1000000.00', 3, '0.216', ['0.005', '0.010', '0.0075', '0.005'], true);

        self::assertSame('1000000.00', $result['base_capital']);
        self::assertSame(3, $result['years']);
        self::assertTrue($result['is_compounded']);
        self::assertSame('0.2160', $result['base_annual_rate']);
        self::assertSame('1831513.43', $result['projected_capital']);
        self::assertSame('831513.43', $result['projected_profit']);
        self::assertSame('277171.14', $result['average_annual_profit']);

        self::assertSame([
            ['year' => 1, 'expected_annual_rate' => '0.2210', 'profit' => '221000.00', 'capital' => '1221000.00'],
            ['year' => 2, 'expected_annual_rate' => '0.2260', 'profit' => '275946.00', 'capital' => '1496946.00'],
            ['year' => 3, 'expected_annual_rate' => '0.2235', 'profit' => '334567.43', 'capital' => '1831513.43'],
        ], $result['schedule']);
    }

    public function test_compounding_reinvests_each_year_profit_on_the_accumulated_balance(): void
    {
        $result = (new RoiCalculatorService(new FinancialRoundingService))
            ->simulate('1000000.00', 4, '0.216', ['0.005', '0.010', '0.0075', '0.005'], true);

        self::assertSame([
            ['year' => 1, 'expected_annual_rate' => '0.2210', 'profit' => '221000.00', 'capital' => '1221000.00'],
            ['year' => 2, 'expected_annual_rate' => '0.2260', 'profit' => '275946.00', 'capital' => '1496946.00'],
            ['year' => 3, 'expected_annual_rate' => '0.2235', 'profit' => '334567.43', 'capital' => '1831513.43'],
            ['year' => 4, 'expected_annual_rate' => '0.2210', 'profit' => '404764.47', 'capital' => '2236277.90'],
        ], $result['schedule']);
    }

    public function test_non_compounded_profit_stays_flat_on_the_original_capital(): void
    {
        $result = (new RoiCalculatorService(new FinancialRoundingService))
            ->simulate('1000000.00', 3, '0.216', ['0.005', '0.010', '0.0075', '0.005'], false);

        self::assertFalse($result['is_compounded']);
        self::assertSame('1670500.00', $result['projected_capital']);
        self::assertSame('670500.00', $result['projected_profit']);
        self::assertSame('223500.00', $result['average_annual_profit']);

        self::assertSame([
            ['year' => 1, 'expected_annual_rate' => '0.2210', 'profit' => '221000.00', 'capital' => '1221000.00'],
            ['year' => 2, 'expected_annual_rate' => '0.2260', 'profit' => '226000.00', 'capital' => '1447000.00'],
            ['year' => 3, 'expected_annual_rate' => '0.2235', 'profit' => '223500.00', 'capital' => '1670500.00'],
        ], $result['schedule']);
    }

    public function test_defaults_apply_base_rate_and_growth_bonus_ladder(): void
    {
        $result = (new RoiCalculatorService(new FinancialRoundingService))
            ->simulate('500000.00', 1);

        self::assertSame('0.2160', $result['base_annual_rate']);
        self::assertSame(['0.0050'], $result['growth_bonus']);
        self::assertSame('0.2210', $result['schedule'][0]['expected_annual_rate']);
        self::assertSame('110500.00', $result['schedule'][0]['profit']);
        self::assertSame('610500.00', $result['projected_capital']);
        self::assertSame('110500.00', $result['average_annual_profit']);
    }

    public function test_short_custom_bonus_ladder_repeats_last_entry(): void
    {
        $result = (new RoiCalculatorService(new FinancialRoundingService))
            ->simulate('1000.00', 3, '0.10', ['0.05'], true);

        self::assertSame([
            ['year' => 1, 'expected_annual_rate' => '0.1500', 'profit' => '150.00', 'capital' => '1150.00'],
            ['year' => 2, 'expected_annual_rate' => '0.1500', 'profit' => '172.50', 'capital' => '1322.50'],
            ['year' => 3, 'expected_annual_rate' => '0.1500', 'profit' => '198.38', 'capital' => '1520.88'],
        ], $result['schedule']);
    }

    public function test_negative_rate_produces_negative_profit_but_never_negative_capital_logic(): void
    {
        $result = (new RoiCalculatorService(new FinancialRoundingService))
            ->simulate('100.00', 1, '-0.20', ['0.000'], true);

        self::assertSame('80.00', $result['projected_capital']);
        self::assertSame('-20.00', $result['projected_profit']);
        self::assertSame('-20.00', $result['average_annual_profit']);
    }

    public function test_empty_growth_bonus_ladder_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RoiCalculatorService(new FinancialRoundingService))
            ->simulate('1000.00', 2, '0.216', [], true);
    }
}
