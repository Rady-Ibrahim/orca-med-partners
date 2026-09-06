<?php

declare(strict_types=1);

namespace Tests\Unit\Financial;

use App\Domain\Financial\ValueObjects\FinancialRoundingService;
use PHPUnit\Framework\TestCase;

class FinancialRoundingTest extends TestCase
{
    public function test_half_even_rounding_handles_ties_and_rates(): void
    {
        $rounding = new FinancialRoundingService();

        self::assertSame('12.34', $rounding->money('12.345'));
        self::assertSame('12.36', $rounding->money('12.355'));
        self::assertSame('0.0250', $rounding->rate('0.025'));
        self::assertSame('100.00', $rounding->money('99.995'));
    }
}
