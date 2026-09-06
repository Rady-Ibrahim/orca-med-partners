<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Participant;

use App\Models\CapitalSnapshotItem;
use App\Models\DepreciationNote;
use App\Models\Investment;
use App\Models\Notification;
use App\Models\Participant;
use App\Models\ParticipantFundAllocation;
use App\Models\ParticipantProfitAllocation;
use App\Models\SettlementItem;
use App\Services\SecurityAuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FinancialResourceController
{
    public function investment(Request $request, Investment $investment): JsonResponse
    {
        $this->authorizeOwner($request, $investment, 'investment');

        return $this->success($investment);
    }

    public function capital(Request $request, CapitalSnapshotItem $capital): JsonResponse
    {
        $this->authorizeOwner($request, $capital, 'capital_snapshot_item');

        return $this->success($capital);
    }

    public function profit(Request $request, ParticipantProfitAllocation $profit): JsonResponse
    {
        $this->authorizeOwner($request, $profit, 'participant_profit_allocation');

        return $this->success($profit);
    }

    public function fund(Request $request, ParticipantFundAllocation $allocation): JsonResponse
    {
        $this->authorizeOwner($request, $allocation, 'participant_fund_allocation');

        return $this->success($allocation);
    }

    public function depreciation(Request $request, DepreciationNote $depreciation): JsonResponse
    {
        $this->authorizeOwner($request, $depreciation, 'depreciation_note');

        return $this->success($depreciation);
    }

    public function settlement(Request $request, SettlementItem $settlement): JsonResponse
    {
        $this->authorizeOwner($request, $settlement, 'settlement_item');

        return $this->success($settlement);
    }

    public function notification(Request $request, Notification $notification): JsonResponse
    {
        $this->authorizeOwner($request, $notification, 'notification');

        return $this->success($notification);
    }

    private function authorizeOwner(Request $request, Model $resource, string $type): void
    {
        $user = $request->user();
        if (! $user instanceof Participant || (int) $resource->getAttribute('participant_id') !== $user->id) {
            app(SecurityAuditService::class)->log('authorization_denied', $user, $type, $resource->getKey(), [
                'action' => 'view',
                'endpoint' => $request->path(),
                'reason' => 'ownership_mismatch',
            ]);
            abort(403, 'Forbidden.');
        }
    }

    private function success(Model $resource): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $resource]);
    }
}
