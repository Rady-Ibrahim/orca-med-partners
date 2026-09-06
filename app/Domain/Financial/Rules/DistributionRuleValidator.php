<?php

declare(strict_types=1);

namespace App\Domain\Financial\Rules;

use InvalidArgumentException;

final class DistributionRuleValidator
{
    /**
     * @param array<string, mixed> $rates
     */
    public static function validate(array $rates): void
    {
        $required = [
            'management_fee_rate',
            'depreciation_fund_rate',
            'growth_fund_rate',
            'incentive_fund_rate',
            'distributed_share_rate',
        ];

        foreach ($required as $key) {
            if (! array_key_exists($key, $rates)) {
                throw new InvalidArgumentException("Missing rate [$key].");
            }

            if (! is_numeric($rates[$key])) {
                throw new InvalidArgumentException("Rate [$key] must be numeric.");
            }

            $value = (float) $rates[$key];

            if ($value < 0 || $value > 1) {
                throw new InvalidArgumentException("Rate [$key] must be between 0 and 1.");
            }
        }

        $total = array_sum(array_map(fn (string $key): float => (float) $rates[$key], $required));

        if (round($total, 4) !== 1.0) {
            throw new InvalidArgumentException('Distribution rule totals must equal 1.0000.');
        }
    }
}
