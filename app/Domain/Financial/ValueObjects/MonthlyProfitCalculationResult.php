<?php

declare(strict_types=1);

namespace App\Domain\Financial\ValueObjects;

final readonly class MonthlyProfitCalculationResult
{
    /** @param array<int, array{participant_id:int, amount:string, share_ratio:string}> $participantAllocations */
    public function __construct(
        public string $grossProfit,
        public array $ruleSnapshot,
        public string $managementAmount,
        public string $depreciationAmount,
        public string $growthAmount,
        public string $incentiveAmount,
        public string $distributedPool,
        public string $totalParticipantCapital,
        public array $participantAllocations,
        public string $roundedAllocationsTotal,
        public string $roundingDelta,
    ) {}
}
