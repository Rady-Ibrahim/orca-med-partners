<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Participant;

use App\Domain\Financial\Services\RoiCalculatorService;
use App\Http\Requests\RoiSimulationRequest;
use App\Models\Participant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RoiSimulationController
{
    public function simulate(RoiSimulationRequest $request, RoiCalculatorService $calculator): JsonResponse
    {
        $this->participant($request);
        $input = $request->validated();

        $result = $calculator->simulate(
            (string) $input['base_capital'],
            (int) $input['years'],
            (string) $input['expected_annual_rate'],
        );

        return response()->json([
            'success' => true,
            'data' => $result + [
                'disclaimer' => 'هذه محاكاة استرشادية عبر فائدة مركبة افتراضية ولا تمثل ضماناً للعائد الفعلي.',
            ],
        ]);
    }

    private function participant(Request $request): Participant
    {
        abort_unless($request->user() instanceof Participant, 403, 'Participant access required.');

        return $request->user();
    }
}