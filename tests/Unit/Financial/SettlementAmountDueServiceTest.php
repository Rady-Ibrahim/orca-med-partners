<?php

declare(strict_types=1);

namespace Tests\Unit\Financial;

use App\Domain\Financial\Services\SettlementAmountDueService;
use App\Domain\Financial\ValueObjects\FinancialRoundingService;
use PHPUnit\Framework\TestCase;

final class SettlementAmountDueServiceTest extends TestCase
{
    public function test_calculates_profit_fund_share_and_previous_payments_without_principal(): void
    {
        $result = (new SettlementAmountDueService(new FinancialRoundingService()))->calculate('120000.00', '30000.00', '20000.00');

        self::assertSame('120000.00', $result['profit']);
        self::assertSame('30000.00', $result['fundShare']);
        self::assertSame('20000.00', $result['previousPayments']);
        self::assertSame('130000.00', $result['amount_due']);
    }

    public function test_zero_and_full_payment_are_decimal_safe(): void
    {
        $service = new SettlementAmountDueService(new FinancialRoundingService());
        self::assertSame('150000.00', $service->calculate('120000', '30000', '0')['amount_due']);
        self::assertSame('0.00', $service->calculate('120000', '30000', '150000')['amount_due']);
    }
}
