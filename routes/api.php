<?php

use App\Http\Controllers\Api\Auth\AdminAuthController;
use App\Http\Controllers\Api\Auth\ParticipantAuthController;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\CapitalSnapshotItem;
use App\Models\DepreciationNote;
use App\Models\Fund;
use App\Models\Investment;
use App\Models\Notification;
use App\Models\Participant;
use App\Models\ParticipantFundAllocation;
use App\Models\ParticipantProfitAllocation;
use App\Models\SettlementItem;
use App\Services\SecurityAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/auth/admin/login', [AdminAuthController::class, 'login']);
    Route::post('/auth/admin/refresh', [AdminAuthController::class, 'refresh']);
    Route::post('/auth/admin/password/reset/request', [AdminAuthController::class, 'requestPasswordReset']);
    Route::post('/auth/admin/password/reset', [AdminAuthController::class, 'resetPassword']);

    Route::post('/auth/participant/login', [ParticipantAuthController::class, 'login']);
    Route::post('/auth/participant/refresh', [ParticipantAuthController::class, 'refresh']);
    Route::post('/auth/participant/password/reset/request', [ParticipantAuthController::class, 'requestPasswordReset']);
    Route::post('/auth/participant/password/reset', [ParticipantAuthController::class, 'resetPassword']);
});

Route::middleware(['ensure.admin.context'])->group(function () {
    Route::post('/auth/admin/logout', [AdminAuthController::class, 'logout']);
    Route::post('/auth/admin/password/change', [AdminAuthController::class, 'changePassword']);

    Route::get('/admin/participants', function (Request $request) {
        $token = $request->bearerToken();
        if (! $token) {
            abort(401, 'Unauthenticated.');
        }
        $accessToken = PersonalAccessToken::findToken($token);
        if (! $accessToken || ! $accessToken->tokenable instanceof Admin) {
            abort(403, 'Admin access required.');
        }

        $user = $request->user();
        abort_unless($user->can('viewAny', Participant::class), 403, 'Forbidden.');

        return response()->json([
            'success' => true,
            'message' => 'Admin participant listing is available.',
            'data' => [
                'user' => $accessToken->tokenable->only(['id', 'username']),
            ],
        ]);
    });

    Route::get('/admin/investments', function (Request $request) {
        $user = $request->user();
        abort_unless($user && $user->can('viewAny', Investment::class), 403, 'Forbidden.');

        return response()->json([
            'success' => true,
            'data' => Investment::query()->with('participant')->get(),
        ]);
    });

    Route::post('/admin/investments/{investment}/approve', function (Request $request, Investment $investment) {
        $user = $request->user();
        abort_unless($user && $user->can('approve', $investment), 403, 'Forbidden.');

        $investment->update([
            'status' => 'approved',
            'approved_by_admin_id' => $user->id,
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $investment->fresh(),
        ]);
    });

    Route::get('/admin/audit-logs', function (Request $request) {
        $user = $request->user();
        abort_unless($user && $user->can('viewAny', AuditLog::class), 403, 'Forbidden.');

        return response()->json([
            'success' => true,
            'data' => AuditLog::query()->latest('id')->limit(50)->get(),
        ]);
    });
});

Route::middleware(['ensure.participant.context'])->group(function () {
    Route::post('/auth/participant/logout', [ParticipantAuthController::class, 'logout']);
    Route::post('/auth/participant/password/change', [ParticipantAuthController::class, 'changePassword']);

    Route::get('/me', function (Request $request) {
        $token = $request->bearerToken();
        if (! $token) {
            abort(401, 'Unauthenticated.');
        }
        $accessToken = PersonalAccessToken::findToken($token);
        if (! $accessToken || ! $accessToken->tokenable instanceof Participant) {
            abort(403, 'Participant access required.');
        }

        return response()->json([
            'success' => true,
            'data' => $accessToken->tokenable->only(['id', 'username']),
        ]);
    });

    Route::get('/participant/investments/{investment}', function (Request $request, Investment $investment) {
        $user = $request->user();
        if (! $user instanceof Participant || $investment->participant_id !== $user->id) {
            app(SecurityAuditService::class)->log('authorization_denied', $user, 'investment', $investment->id, [
                'action' => 'view',
                'endpoint' => '/participant/investments/{investment}',
                'reason' => 'ownership_mismatch',
            ]);
            abort(403, 'Forbidden.');
        }

        return response()->json([
            'success' => true,
            'data' => $investment,
        ]);
    });

    Route::patch('/participant/investments/{investment}', function (Request $request, Investment $investment) {
        $user = $request->user();
        if (! $user instanceof Participant || $investment->participant_id !== $user->id) {
            app(SecurityAuditService::class)->log('authorization_denied', $user, 'investment', $investment->id, [
                'action' => 'update',
                'endpoint' => '/participant/investments/{investment}',
                'reason' => 'ownership_mismatch',
            ]);
            abort(403, 'Forbidden.');
        }

        if ($request->has('participant_id') && (int) $request->input('participant_id') !== $user->id) {
            abort(403, 'Forbidden.');
        }

        $payload = $request->only(['amount', 'notes']);
        $investment->fill($payload);
        $investment->save();

        return response()->json([
            'success' => true,
            'data' => $investment->fresh(),
        ]);
    });

    Route::get('/participant/capital/{capital}', function (Request $request, CapitalSnapshotItem $capital) {
        $user = $request->user();
        if (! $user instanceof Participant || $capital->participant_id !== $user->id) {
            app(SecurityAuditService::class)->log('authorization_denied', $user, 'capital_snapshot_item', $capital->id, [
                'action' => 'view',
                'endpoint' => '/participant/capital/{capital}',
                'reason' => 'ownership_mismatch',
            ]);
            abort(403, 'Forbidden.');
        }

        return response()->json(['success' => true, 'data' => $capital]);
    });

    Route::patch('/participant/capital/{capital}', function (Request $request, CapitalSnapshotItem $capital) {
        $user = $request->user();
        if (! $user instanceof Participant || $capital->participant_id !== $user->id) {
            app(SecurityAuditService::class)->log('authorization_denied', $user, 'capital_snapshot_item', $capital->id, [
                'action' => 'update',
                'endpoint' => '/participant/capital/{capital}',
                'reason' => 'ownership_mismatch',
            ]);
            abort(403, 'Forbidden.');
        }

        if ($request->has('participant_id') && (int) $request->input('participant_id') !== $user->id) {
            abort(403, 'Forbidden.');
        }

        $capital->fill($request->only(['participant_capital_snapshot', 'calculation_metadata']));
        $capital->save();

        return response()->json(['success' => true, 'data' => $capital->fresh()]);
    });

    Route::get('/participant/profits/{profit}', function (Request $request, ParticipantProfitAllocation $profit) {
        $user = $request->user();
        if (! $user instanceof Participant || $profit->participant_id !== $user->id) {
            app(SecurityAuditService::class)->log('authorization_denied', $user, 'participant_profit_allocation', $profit->id, [
                'action' => 'view',
                'endpoint' => '/participant/profits/{profit}',
                'reason' => 'ownership_mismatch',
            ]);
            abort(403, 'Forbidden.');
        }

        return response()->json(['success' => true, 'data' => $profit]);
    });

    Route::patch('/participant/profits/{profit}', function (Request $request, ParticipantProfitAllocation $profit) {
        $user = $request->user();
        if (! $user instanceof Participant || $profit->participant_id !== $user->id) {
            app(SecurityAuditService::class)->log('authorization_denied', $user, 'participant_profit_allocation', $profit->id, [
                'action' => 'update',
                'endpoint' => '/participant/profits/{profit}',
                'reason' => 'ownership_mismatch',
            ]);
            abort(403, 'Forbidden.');
        }

        if ($request->has('participant_id') && (int) $request->input('participant_id') !== $user->id) {
            abort(403, 'Forbidden.');
        }

        $profit->fill($request->only(['amount', 'share_ratio']));
        $profit->save();

        return response()->json(['success' => true, 'data' => $profit->fresh()]);
    });

    Route::get('/participant/funds/{allocation}', function (Request $request, ParticipantFundAllocation $allocation) {
        $user = $request->user();
        if (! $user instanceof Participant || $allocation->participant_id !== $user->id) {
            app(SecurityAuditService::class)->log('authorization_denied', $user, 'participant_fund_allocation', $allocation->id, [
                'action' => 'view',
                'endpoint' => '/participant/funds/{allocation}',
                'reason' => 'ownership_mismatch',
            ]);
            abort(403, 'Forbidden.');
        }

        return response()->json(['success' => true, 'data' => $allocation]);
    });

    Route::patch('/participant/funds/{allocation}', function (Request $request, ParticipantFundAllocation $allocation) {
        $user = $request->user();
        if (! $user instanceof Participant || $allocation->participant_id !== $user->id) {
            app(SecurityAuditService::class)->log('authorization_denied', $user, 'participant_fund_allocation', $allocation->id, [
                'action' => 'update',
                'endpoint' => '/participant/funds/{allocation}',
                'reason' => 'ownership_mismatch',
            ]);
            abort(403, 'Forbidden.');
        }

        if ($request->has('participant_id') && (int) $request->input('participant_id') !== $user->id) {
            abort(403, 'Forbidden.');
        }

        $allocation->fill($request->only(['amount', 'allocation_type']));
        $allocation->save();

        return response()->json(['success' => true, 'data' => $allocation->fresh()]);
    });

    Route::get('/participant/depreciation/{depreciation}', function (Request $request, DepreciationNote $depreciation) {
        $user = $request->user();
        if (! $user instanceof Participant || $depreciation->participant_id !== $user->id) {
            app(SecurityAuditService::class)->log('authorization_denied', $user, 'depreciation_note', $depreciation->id, [
                'action' => 'view',
                'endpoint' => '/participant/depreciation/{depreciation}',
                'reason' => 'ownership_mismatch',
            ]);
            abort(403, 'Forbidden.');
        }

        return response()->json(['success' => true, 'data' => $depreciation]);
    });

    Route::patch('/participant/depreciation/{depreciation}', function (Request $request, DepreciationNote $depreciation) {
        $user = $request->user();
        if (! $user instanceof Participant || $depreciation->participant_id !== $user->id) {
            app(SecurityAuditService::class)->log('authorization_denied', $user, 'depreciation_note', $depreciation->id, [
                'action' => 'update',
                'endpoint' => '/participant/depreciation/{depreciation}',
                'reason' => 'ownership_mismatch',
            ]);
            abort(403, 'Forbidden.');
        }

        if ($request->has('participant_id') && (int) $request->input('participant_id') !== $user->id) {
            abort(403, 'Forbidden.');
        }

        $depreciation->fill($request->only(['amount', 'description']));
        $depreciation->save();

        return response()->json(['success' => true, 'data' => $depreciation->fresh()]);
    });

    Route::get('/participant/settlements/{settlement}', function (Request $request, SettlementItem $settlement) {
        $user = $request->user();
        if (! $user instanceof Participant || $settlement->participant_id !== $user->id) {
            app(SecurityAuditService::class)->log('authorization_denied', $user, 'settlement_item', $settlement->id, [
                'action' => 'view',
                'endpoint' => '/participant/settlements/{settlement}',
                'reason' => 'ownership_mismatch',
            ]);
            abort(403, 'Forbidden.');
        }

        return response()->json(['success' => true, 'data' => $settlement]);
    });

    Route::patch('/participant/settlements/{settlement}', function (Request $request, SettlementItem $settlement) {
        $user = $request->user();
        if (! $user instanceof Participant || $settlement->participant_id !== $user->id) {
            app(SecurityAuditService::class)->log('authorization_denied', $user, 'settlement_item', $settlement->id, [
                'action' => 'update',
                'endpoint' => '/participant/settlements/{settlement}',
                'reason' => 'ownership_mismatch',
            ]);
            abort(403, 'Forbidden.');
        }

        if ($request->has('participant_id') && (int) $request->input('participant_id') !== $user->id) {
            abort(403, 'Forbidden.');
        }

        $settlement->fill($request->only(['net_payable', 'payment_status']));
        $settlement->save();

        return response()->json(['success' => true, 'data' => $settlement->fresh()]);
    });

    Route::get('/participant/notifications/{notification}', function (Request $request, Notification $notification) {
        $user = $request->user();
        if (! $user instanceof Participant || $notification->participant_id !== $user->id) {
            app(SecurityAuditService::class)->log('authorization_denied', $user, 'notification', $notification->id, [
                'action' => 'view',
                'endpoint' => '/participant/notifications/{notification}',
                'reason' => 'ownership_mismatch',
            ]);
            abort(403, 'Forbidden.');
        }

        return response()->json(['success' => true, 'data' => $notification]);
    });

    Route::patch('/participant/notifications/{notification}', function (Request $request, Notification $notification) {
        $user = $request->user();
        if (! $user instanceof Participant || $notification->participant_id !== $user->id) {
            app(SecurityAuditService::class)->log('authorization_denied', $user, 'notification', $notification->id, [
                'action' => 'update',
                'endpoint' => '/participant/notifications/{notification}',
                'reason' => 'ownership_mismatch',
            ]);
            abort(403, 'Forbidden.');
        }

        if ($request->has('participant_id') && (int) $request->input('participant_id') !== $user->id) {
            abort(403, 'Forbidden.');
        }

        $notification->fill($request->only(['is_read', 'title', 'body']));
        $notification->save();

        return response()->json(['success' => true, 'data' => $notification->fresh()]);
    });
});
