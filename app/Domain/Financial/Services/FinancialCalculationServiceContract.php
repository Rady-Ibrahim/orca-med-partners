<?php

declare(strict_types=1);

namespace App\Domain\Financial\Services;

use App\Domain\Financial\ValueObjects\MonthlyProfitCalculationResult;
use App\Models\CapitalSnapshot;
use App\Models\DistributionRule;

interface FinancialCalculationServiceContract
{
    /** @param array<string, string>|null $ruleSnapshot */
    public function calculate(string|int $grossProfit, DistributionRule $rule, CapitalSnapshot $snapshot, ?array $ruleSnapshot = null): MonthlyProfitCalculationResult;
}
