<?php

declare(strict_types=1);

namespace Tests\Unit\Financial;

use App\Domain\Financial\Exceptions\InvalidCapitalSnapshotException;
use App\Domain\Financial\Services\CapitalCalculatorService;
use App\Domain\Financial\ValueObjects\FinancialRoundingService;
use PHPUnit\Framework\TestCase;

final class CapitalCalculatorServiceTest extends TestCase
{
    private CapitalCalculatorService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CapitalCalculatorService(new FinancialRoundingService);
    }

    public function test_ratios_always_sum_to_exactly_one_even_when_truncation_would_lose_precision(): void
    {
        // 7,000,000 / 70,000,004 truncates to 0.0999. Summing naive
        // truncations here produced 0.9995 and silently under-paid partners.
        $ratios = $this->service->recalculateRatios([
            1 => '6000001.00',
            2 => '2000000.00',
            3 => '1000000.00',
            4 => '1000000.00',
            5 => '2000000.00',
            6 => '51000000.00',
            7 => '0.00',
            8 => '7000000.00',
        ]);

        self::assertSame('1.0000', $this->sumRatios($ratios));
        self::assertSame('0.1000', $ratios[8]);
    }

    public function test_residual_is_applied_to_the_largest_holder(): void
    {
        // All three truncate to 0.3333, so the residual is carried by the
        // lowest id under the deterministic tie-break.
        $ratios = $this->service->recalculateRatios([1 => '333.33', 2 => '333.33', 3 => '333.34']);

        self::assertSame('1.0000', $this->sumRatios($ratios));
        self::assertSame('0.3334', $ratios[1]);
        self::assertSame('0.3333', $ratios[2]);
        self::assertSame('0.3333', $ratios[3]);
    }

    public function test_three_way_even_split_is_exact(): void
    {
        $ratios = $this->service->recalculateRatios([1 => '1000.00', 2 => '1000.00', 3 => '1000.00']);

        self::assertSame('1.0000', $this->sumRatios($ratios));
    }

    public function test_tie_on_largest_holder_is_broken_deterministically_by_lowest_id(): void
    {
        $first = $this->service->recalculateRatios([7 => '500.00', 3 => '500.00', 9 => '1.00']);
        $second = $this->service->recalculateRatios([9 => '1.00', 3 => '500.00', 7 => '500.00']);

        ksort($first);
        ksort($second);

        self::assertSame($first, $second, 'Ratio assignment must not depend on input or row ordering.');
        self::assertSame('1.0000', $this->sumRatios($first));
        self::assertSame('0.4995', $first[3], 'Lowest participant id absorbs the residual.');
        self::assertSame('0.4995', $first[7]);
    }

    public function test_zero_total_yields_zero_ratios_rather_than_dividing_by_zero(): void
    {
        $ratios = $this->service->recalculateRatios([1 => '0.00', 2 => '0.00']);

        self::assertSame(['1' => '0.0000', '2' => '0.0000'], $ratios);
    }

    public function test_empty_input_returns_empty_ratios(): void
    {
        self::assertSame([], $this->service->recalculateRatios([]));
    }

    public function test_a_partner_with_no_capital_still_gets_a_zero_ratio_row(): void
    {
        $ratios = $this->service->recalculateRatios([1 => '1000000.00', 2 => '0.00']);

        self::assertSame('0.0000', $ratios[2]);
        self::assertSame('1.0000', $ratios[1]);
        self::assertSame('1.0000', $this->sumRatios($ratios));
    }

    public function test_negative_capital_is_rejected(): void
    {
        $this->expectException(InvalidCapitalSnapshotException::class);

        $this->service->recalculateRatios([1 => '100.00', 2 => '-1.00']);
    }

    public function test_negative_capital_is_rejected_when_normalizing_items(): void
    {
        $this->expectException(InvalidCapitalSnapshotException::class);

        $this->service->normalizeItems([['participant_id' => 1, 'capital' => '-5.00']]);
    }

    public function test_garbage_capital_is_rejected(): void
    {
        $this->expectException(InvalidCapitalSnapshotException::class);

        $this->service->recalculateRatios([1 => 'not-a-number']);
    }

    public function test_item_without_participant_reference_is_rejected(): void
    {
        $this->expectException(InvalidCapitalSnapshotException::class);

        $this->service->normalizeItems([['participant_id' => 0, 'capital' => '100.00']]);
    }

    public function test_normalize_items_rounds_money_half_even_and_totals_exactly(): void
    {
        $result = $this->service->normalizeItems([
            ['participant_id' => 1, 'capital' => '1000.005'],
            ['participant_id' => 2, 'capital' => 2000.5],
            ['participant_id' => 3, 'capital' => '0.004'],
            ['participant_id' => 4, 'capital' => '0.015'],
        ]);

        // Half-even: 1000.005 -> 1000.00 and 0.005 -> 0.00 (last kept digit is
        // even), while 0.015 -> 0.02 (last kept digit is odd).
        self::assertSame('1000.00', $result['items'][0]['capital']);
        self::assertSame('2000.50', $result['items'][1]['capital']);
        self::assertSame('0.00', $result['items'][2]['capital']);
        self::assertSame('0.02', $result['items'][3]['capital']);
        self::assertSame('3000.52', $result['total_capital']);
        self::assertCount(4, $result['items']);
    }

    public function test_duplicate_participant_rows_collapse_and_the_total_matches_the_survivor(): void
    {
        $result = $this->service->normalizeItems([
            ['participant_id' => 1, 'capital' => '100.00'],
            ['participant_id' => 1, 'capital' => '250.00'],
        ]);

        self::assertCount(1, $result['items']);
        self::assertSame('250.00', $result['items'][0]['capital']);
        self::assertSame('250.00', $result['total_capital']);
    }

    /**
     * @param  array<int, string>  $ratios
     */
    private function sumRatios(array $ratios): string
    {
        return array_reduce(
            $ratios,
            static fn (string $carry, string $ratio): string => bcadd($carry, $ratio, 4),
            '0.0000',
        );
    }
}
