<?php

declare(strict_types=1);

namespace App\Domain\Financial\Services;

use App\Domain\Financial\Rules\DistributionRuleValidator;
use App\Models\DistributionRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

final class DistributionRuleService
{
    public function activeRule(?CarbonInterface $at = null): ?DistributionRule
    {
        $date = $at ?? Date::today();

        return DistributionRule::query()
            ->where('status', 'active')
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date) {
                $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            })
            ->orderBy('effective_from', 'desc')
            ->first();
    }

    /**
     * @param array<string, mixed> $rates
     */
    public function validateRates(array $rates): void
    {
        DistributionRuleValidator::validate($rates);
    }
}
