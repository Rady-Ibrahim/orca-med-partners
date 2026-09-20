<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Participant;

use App\Domain\Financial\Services\RoiCalculatorService;
use App\Http\Requests\RoiSimulationRequest;
use App\Models\Participant;
use App\Support\AppSettingBag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RoiSimulationController
{
    public function simulate(RoiSimulationRequest $request, RoiCalculatorService $calculator): JsonResponse
    {
        $this->participant($request);
        $input = $request->validated();

        $baseRate = (string) AppSettingBag::get('roi_base_annual_rate', RoiCalculatorService::DEFAULT_BASE_ANNUAL_RATE);
        $bonuses = (array) AppSettingBag::get('roi_growth_bonuses', RoiCalculatorService::DEFAULT_GROWTH_BONUSES);

        $result = $calculator->simulate(
            (string) $input['base_capital'],
            (int) $input['years'],
            (string) ($input['base_annual_rate'] ?? $baseRate),
            array_map('strval', $input['growth_bonus'] ?? $bonuses),
            (bool) ($input['is_compounded'] ?? true),
        );

        return response()->json([
            'success' => true,
            'data' => $result + [
                'disclaimer' => 'هذه محاكاة استرشادية لأرباح سنوية مركبة تعتمد على معدل فاعلية سنوي افتراضي ولا تمثل ضماناً للعائد الفعلي.',
            ],
        ]);
    }

    private function participant(Request $request): Participant
    {
        abort_unless($request->user() instanceof Participant, 403, 'Participant access required.');

        return $request->user();
    }
}
