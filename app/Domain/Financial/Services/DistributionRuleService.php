<?php

declare(strict_types=1);

namespace App\Domain\Financial\Services;

use App\Domain\Financial\Rules\DistributionRuleValidator;
use App\Models\DistributionRule;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use InvalidArgumentException;

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

    public function validateEffectiveRange(string $effectiveFrom, ?string $effectiveTo, ?int $ignoreId = null, bool $allowOpenEnded = true): void
    {
        $from = Date::parse($effectiveFrom);
        $to = $effectiveTo !== null && $effectiveTo !== '' ? Date::parse($effectiveTo) : null;

        if ($to !== null && $from->greaterThan($to)) {
            throw new InvalidArgumentException('The effective start date cannot be after the end date.');
        }

        $query = DistributionRule::query()
            ->where('status', 'active');

        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        $rows = $query->get();

        foreach ($rows as $rule) {
            $existingFrom = Date::parse($rule->effective_from);
            $existingTo = $rule->effective_to !== null && $rule->effective_to !== '' ? Date::parse($rule->effective_to) : null;

            $newFrom = $from->toDateString();
            $newTo = $to?->toDateString();
            $existingFromString = $existingFrom->toDateString();
            $existingToString = $existingTo?->toDateString();

            $overlaps = false;

            if ($newTo !== null && $existingToString !== null) {
                $overlaps = $existingFrom->lessThanOrEqualTo($to) && $existingTo->greaterThanOrEqualTo($from);
            } elseif ($newTo === null && $existingToString !== null) {
                $overlaps = $existingFrom->lessThanOrEqualTo($from) && $existingTo->greaterThanOrEqualTo($from);
            } elseif ($newTo !== null && $existingToString === null) {
                $overlaps = $existingFrom->lessThanOrEqualTo($to) && $existingFrom->lessThanOrEqualTo($from);
            } else {
                $overlaps = $allowOpenEnded;
            }

            if ($overlaps) {
                throw new InvalidArgumentException('Overlapping active distribution rule periods are not allowed.');
            }
        }
    }

    /**
     * @param array<string, mixed> $rates
     */
    public function validateRates(array $rates): void
    {
        DistributionRuleValidator::validate($rates);
    }
}
