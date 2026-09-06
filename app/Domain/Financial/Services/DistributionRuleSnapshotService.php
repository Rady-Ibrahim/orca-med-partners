<?php

declare(strict_types=1);

namespace App\Domain\Financial\Services;

use App\Models\DistributionRule;

final class DistributionRuleSnapshotService
{
    /** @return array<string, string> */
    public function snapshot(DistributionRule $rule): array
    {
        return [
            'management_fee_rate' => (string) $rule->management_fee_rate,
            'depreciation_fund_rate' => (string) $rule->depreciation_fund_rate,
            'growth_fund_rate' => (string) $rule->growth_fund_rate,
            'incentive_fund_rate' => (string) $rule->incentive_fund_rate,
            'distributed_share_rate' => (string) $rule->distributed_share_rate,
        ];
    }
}
