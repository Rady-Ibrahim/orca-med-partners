<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Participant;

use App\Domain\Financial\Services\ProfitProjectionService;
use App\Http\Requests\ProfitProjectionRequest;
use App\Models\ProfitProjectionLog;
use Illuminate\Http\JsonResponse;

final class ProfitProjectionController
{
    public function project(ProfitProjectionRequest $request, ProfitProjectionService $service): JsonResponse
    {
        $input = $request->validated();

        $data = $service->project(
            (string) $input['amount'],
            (string) $input['period_type'],
            (int) $input['period_value'],
            (bool) ($input['is_compounded'] ?? false),
        );

        ProfitProjectionLog::query()->create([
            'participant_id' => $request->user('sanctum')?->getKey(),
            'amount' => $data['initial_amount'],
            'period_type' => $input['period_type'],
            'period_value' => (int) $input['period_value'],
            'is_compounded' => (bool) ($input['is_compounded'] ?? false),
            'expected_net_profit' => $data['expected_net_profit'],
            'expected_total_balance' => $data['expected_total_balance'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
