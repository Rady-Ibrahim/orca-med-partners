<?php

use App\Http\Controllers\Api\Admin\AuditLogController;
use App\Http\Controllers\Api\Admin\FundController;
use App\Http\Controllers\Api\Admin\FundTransactionController;
use App\Http\Controllers\Api\Admin\InvestmentController;
use App\Http\Controllers\Api\Admin\ParticipantController;
use App\Http\Controllers\Api\Auth\AdminAuthController;
use App\Http\Controllers\Api\Auth\ParticipantAuthController;
use App\Http\Controllers\Api\Financial\MonthlyProfitController;
use App\Http\Controllers\Api\Participant\FinancialResourceController;
use App\Http\Controllers\Api\Participant\MeController;
use Illuminate\Support\Facades\Route;

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

Route::middleware('ensure.admin.context')->group(function () {
    Route::post('/auth/admin/logout', [AdminAuthController::class, 'logout']);
    Route::post('/auth/admin/password/change', [AdminAuthController::class, 'changePassword']);

    Route::get('/admin/participants', [ParticipantController::class, 'index']);
    Route::get('/admin/investments', [InvestmentController::class, 'index']);
    Route::post('/admin/investments/{investment}/approve', [InvestmentController::class, 'approve']);
    Route::get('/admin/audit-logs', [AuditLogController::class, 'index']);
    Route::get('/admin/funds', [FundController::class, 'index']);
    Route::post('/admin/funds', [FundController::class, 'store']);
    Route::get('/admin/funds/{fund}', [FundController::class, 'show']);
    Route::patch('/admin/funds/{fund}', [FundController::class, 'update']);
    Route::get('/admin/funds/{fund}/transactions', [FundTransactionController::class, 'index']);
    Route::post('/admin/funds/{fund}/transactions', [FundTransactionController::class, 'store']);
    Route::get('/admin/funds/{fund}/transactions/{transaction}', [FundTransactionController::class, 'show']);
    Route::get('/admin/funds/{fund}/reconciliation', [FundTransactionController::class, 'reconcile']);

    Route::post('/admin/monthly-profits', [MonthlyProfitController::class, 'store']);
    Route::post('/admin/monthly-profits/{monthlyProfit}/approve', [MonthlyProfitController::class, 'approve']);
    Route::post('/admin/monthly-profits/{monthlyProfit}/revision', [MonthlyProfitController::class, 'revise']);
});

Route::middleware('ensure.participant.context')->group(function () {
    Route::post('/auth/participant/logout', [ParticipantAuthController::class, 'logout']);
    Route::post('/auth/participant/password/change', [ParticipantAuthController::class, 'changePassword']);

    Route::get('/me', [MeController::class, 'show']);
    Route::get('/participant/investments/{investment}', [FinancialResourceController::class, 'investment']);
    Route::get('/participant/monthly-profits/{monthlyProfit}', [MonthlyProfitController::class, 'showForParticipant']);
    Route::get('/participant/capital/{capital}', [FinancialResourceController::class, 'capital']);
    Route::get('/participant/profits/{profit}', [FinancialResourceController::class, 'profit']);
    Route::get('/participant/funds/{allocation}', [FinancialResourceController::class, 'fund']);
    Route::get('/participant/depreciation/{depreciation}', [FinancialResourceController::class, 'depreciation']);
    Route::get('/participant/settlements/{settlement}', [FinancialResourceController::class, 'settlement']);
    Route::get('/participant/notifications/{notification}', [FinancialResourceController::class, 'notification']);
});
