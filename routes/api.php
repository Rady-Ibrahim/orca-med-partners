<?php

use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\CapitalController;
use App\Http\Controllers\Api\Admin\DepreciationController;
use App\Http\Controllers\Api\Admin\DistributionRuleController;
use App\Http\Controllers\Api\Admin\FundController;
use App\Http\Controllers\Api\Admin\FundTransactionController;
use App\Http\Controllers\Api\Admin\InvestmentController;
use App\Http\Controllers\Api\Admin\ParticipantController;
use App\Http\Controllers\Api\Admin\SettingsController;
use App\Http\Controllers\Api\Admin\SettlementController as AdminSettlementController;
use App\Http\Controllers\Api\Auth\AdminAuthController;
use App\Http\Controllers\Api\Auth\ParticipantAuthController;
use App\Http\Controllers\Api\Financial\MonthlyProfitController;
use App\Http\Controllers\Api\LegalController;
use App\Http\Controllers\Api\Participant\DashboardController;
use App\Http\Controllers\Api\Participant\FinancialResourceController;
use App\Http\Controllers\Api\Participant\MeController;
use App\Http\Controllers\Api\Participant\NotificationActionController;
use App\Http\Controllers\Api\Participant\ProfitProjectionController;
use App\Http\Controllers\Api\Participant\ReceiptController;
use App\Http\Controllers\Api\Participant\ReportController;
use App\Http\Controllers\Api\Participant\RoiSimulationController;
use App\Http\Controllers\Api\Participant\SecuritySettingsController;
use App\Http\Controllers\Api\Participant\SettlementController as ParticipantSettlementController;
use App\Http\Controllers\Api\Participant\SupportTicketController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
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

    Route::middleware('throttle:60,1')->group(function () {
        Route::get('/legal/terms', [LegalController::class, 'terms']);
    });

    Route::middleware('throttle:30,1')->group(function () {
        Route::post('/partner/profit-projection', [ProfitProjectionController::class, 'project']);
    });

    Route::middleware('ensure.api.context')->group(function () {
        Route::post('/auth/admin/logout', [AdminAuthController::class, 'logout']);
        Route::post('/auth/admin/password/change', [AdminAuthController::class, 'changePassword']);

        Route::get('/admin/participants', [ParticipantController::class, 'index']);
        Route::post('/admin/participants', [ParticipantController::class, 'store']);
        Route::get('/admin/participants/{participant}', [ParticipantController::class, 'show']);
        Route::put('/admin/participants/{participant}', [ParticipantController::class, 'update']);
        Route::delete('/admin/participants/{participant}', [ParticipantController::class, 'destroy']);
        Route::get('/admin/investments', [InvestmentController::class, 'index']);
        Route::post('/admin/investments', [InvestmentController::class, 'store']);
        Route::post('/admin/investments/{investment}/approve', [InvestmentController::class, 'approve']);
        Route::get('/admin/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/admin/capital', [CapitalController::class, 'index']);
        Route::post('/admin/capital', [CapitalController::class, 'store']);
        Route::patch('/admin/capital/{capitalSnapshot}', [CapitalController::class, 'update']);
        Route::get('/admin/depreciation', [DepreciationController::class, 'index']);
        Route::post('/admin/depreciation', [DepreciationController::class, 'store']);
        Route::patch('/admin/depreciation/{depreciationNote}', [DepreciationController::class, 'update']);
        Route::delete('/admin/depreciation/{depreciationNote}', [DepreciationController::class, 'destroy']);
        Route::get('/admin/distribution-rules', [DistributionRuleController::class, 'index']);
        Route::get('/admin/distribution-rules/{distributionRule}', [DistributionRuleController::class, 'show']);
        Route::post('/admin/distribution-rules', [DistributionRuleController::class, 'store']);
        Route::patch('/admin/distribution-rules/{distributionRule}', [DistributionRuleController::class, 'update']);
        Route::get('/admin/settings', [SettingsController::class, 'index']);
        Route::put('/admin/settings', [SettingsController::class, 'update']);
        Route::get('/admin/monthly-profits', [MonthlyProfitController::class, 'index']);
        Route::get('/admin/funds', [FundController::class, 'index']);
        Route::post('/admin/funds', [FundController::class, 'store']);
        Route::get('/admin/funds/{fund}', [FundController::class, 'show']);
        Route::patch('/admin/funds/{fund}', [FundController::class, 'update']);
        Route::get('/admin/funds/{fund}/transactions', [FundTransactionController::class, 'index']);
        Route::post('/admin/funds/{fund}/transactions', [FundTransactionController::class, 'store']);
        Route::get('/admin/funds/{fund}/transactions/{transaction}', [FundTransactionController::class, 'show']);
        Route::get('/admin/funds/{fund}/reconciliation', [FundTransactionController::class, 'reconcile']);
        Route::get('/admin/settlements', [AdminSettlementController::class, 'index']);
        Route::post('/admin/settlements', [AdminSettlementController::class, 'store']);
        Route::get('/admin/settlements/{settlement}', [AdminSettlementController::class, 'show']);
        Route::post('/admin/settlements/{settlement}/approve', [AdminSettlementController::class, 'approve']);
        Route::post('/admin/settlements/{settlement}/paid', [AdminSettlementController::class, 'paid']);
        Route::post('/admin/settlements/{settlement}/payments', [AdminSettlementController::class, 'payment']);
        Route::post('/admin/settlements/{settlement}/adjustments', [AdminSettlementController::class, 'adjustment']);
        Route::post('/admin/settlements/{settlement}/cancel', [AdminSettlementController::class, 'cancel']);
        Route::post('/admin/settlements/{settlement}/revision', [AdminSettlementController::class, 'revise']);

        Route::post('/admin/monthly-profits', [MonthlyProfitController::class, 'store']);
        Route::post('/admin/monthly-profits/{monthlyProfit}/approve', [MonthlyProfitController::class, 'approve']);
        Route::post('/admin/monthly-profits/{monthlyProfit}/revision', [MonthlyProfitController::class, 'revise']);

        Route::post('/auth/participant/logout', [ParticipantAuthController::class, 'logout']);
        Route::post('/auth/participant/password/change', [ParticipantAuthController::class, 'changePassword']);
        Route::post('/auth/participant/change-password', [ParticipantAuthController::class, 'changePassword']);

        Route::get('/me', [MeController::class, 'show']);
        Route::get('/me/investment', [DashboardController::class, 'investment']);
        Route::get('/me/capital', [DashboardController::class, 'capital']);
        Route::get('/me/capital/growth', [DashboardController::class, 'capitalGrowth']);
        Route::get('/me/profits', [DashboardController::class, 'profits']);
        Route::get('/me/financial/summary', [DashboardController::class, 'financialSummary']);
        Route::get('/me/funds', [DashboardController::class, 'funds']);
        Route::get('/me/depreciation', [DashboardController::class, 'depreciation']);
        Route::get('/me/settlements', [DashboardController::class, 'settlements']);
        Route::get('/me/settlements/{settlement}', [DashboardController::class, 'settlement']);
        Route::get('/me/settlements/{settlement}/payments', [DashboardController::class, 'settlementPayments']);
        Route::get('/me/notifications', [DashboardController::class, 'notifications']);
        Route::get('/me/notifications/unread-count', [NotificationActionController::class, 'unreadCount']);
        Route::patch('/me/notifications/read-all', [NotificationActionController::class, 'markAllRead']);
        Route::patch('/me/notifications/{notification}/read', [NotificationActionController::class, 'markRead']);

        Route::get('/me/reports', [ReportController::class, 'index']);
        Route::get('/me/reports/{type}/download', [ReportController::class, 'download']);
        Route::get('/me/settlements/{settlement}/payments/{payment}/receipt', [ReceiptController::class, 'show']);

        Route::post('/me/tools/roi-simulation', [RoiSimulationController::class, 'simulate']);

        Route::get('/me/settings/2fa', [SecuritySettingsController::class, 'show2fa']);
        Route::post('/me/settings/2fa/toggle', [SecuritySettingsController::class, 'toggle2fa']);

        Route::post('/support/ticket', [SupportTicketController::class, 'store']);

        Route::get('/participant/investments/{investment}', [FinancialResourceController::class, 'investment']);
        Route::get('/participant/monthly-profits/{monthlyProfit}', [MonthlyProfitController::class, 'showForParticipant']);
        Route::get('/participant/capital/{capital}', [FinancialResourceController::class, 'capital']);
        Route::get('/participant/profits/{profit}', [FinancialResourceController::class, 'profit']);
        Route::get('/participant/funds/{allocation}', [FinancialResourceController::class, 'fund']);
        Route::get('/participant/depreciation/{depreciation}', [FinancialResourceController::class, 'depreciation']);
        Route::get('/participant/settlements', [ParticipantSettlementController::class, 'index']);
        Route::get('/participant/settlements/{settlement}', [ParticipantSettlementController::class, 'show']);
        Route::get('/participant/notifications/{notification}', [FinancialResourceController::class, 'notification']);
    });
});
