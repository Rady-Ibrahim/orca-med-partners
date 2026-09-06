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

            $value = (string) $rates[$key];

            if (! preg_match('/^\d+(\.\d+)?$/', $value)) {
                throw new InvalidArgumentException("Rate [$key] must be numeric.");
            }

            if (bccomp($value, '0', 4) < 0 || bccomp($value, '1', 4) > 0) {
                throw new InvalidArgumentException("Rate [$key] must be between 0 and 1.");
            }
        }

        $total = '0';
        foreach ($required as $key) {
            $total = bcadd($total, (string) $rates[$key], 4);
        }

        if (bccomp($total, '1.0000', 4) !== 0) {
            throw new InvalidArgumentException('Distribution rule totals must equal 1.0000.');
        }
    }
}
