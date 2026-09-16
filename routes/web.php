<?php

use App\Http\Controllers\Admin\SidebarPageController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\AdminParticipantController;
use App\Http\Controllers\Admin\AdminActionsController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminWebAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/login', [AdminWebAuthController::class, 'login'])->name('admin.login');
Route::post('/admin/login', [AdminWebAuthController::class, 'authenticate'])->middleware('throttle:10,1')->name('admin.login.submit');
Route::post('/admin/logout', [AdminWebAuthController::class, 'logout'])->middleware('ensure.web.admin')->name('admin.logout');

Route::middleware('ensure.web.admin')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
    Route::get('/participants', [SidebarPageController::class, 'participants'])->name('participants');
    Route::get('/participants/create', [AdminParticipantController::class, 'create'])->name('participants.create');
    Route::post('/participants', [AdminParticipantController::class, 'store'])->name('participants.store');
    Route::get('/participants/{participant}/edit', [AdminParticipantController::class, 'edit'])->name('participants.edit');
    Route::put('/participants/{participant}', [AdminParticipantController::class, 'update'])->name('participants.update');
    Route::get('/participants/{participant}/password', [AdminParticipantController::class, 'password'])->name('participants.password');
    Route::put('/participants/{participant}/password', [AdminParticipantController::class, 'passwordUpdate'])->name('participants.password.update');
    Route::post('/participants/{participant}/revoke-tokens', [AdminParticipantController::class, 'revokeTokens'])->name('participants.revoke-tokens');
    Route::get('/investments', [SidebarPageController::class, 'investments'])->name('investments');
    Route::post('/investments', [AdminActionsController::class, 'storeInvestment'])->name('investments.store');
    Route::post('/investments/{investment}/approve', [AdminActionsController::class, 'approveInvestment'])->name('investments.approve');
    Route::get('/capital', [SidebarPageController::class, 'capital'])->name('capital');
    Route::post('/capital', [AdminActionsController::class, 'storeCapitalSnapshot'])->name('capital.store');
    Route::get('/monthly-profits', [SidebarPageController::class, 'monthlyProfits'])->name('monthly-profits');
    Route::post('/monthly-profits', [AdminActionsController::class, 'storeMonthlyProfit'])->name('monthly-profits.store');
    Route::post('/monthly-profits/{monthlyProfit}/approve', [AdminActionsController::class, 'approveMonthlyProfit'])->name('monthly-profits.approve');
    Route::post('/monthly-profits/{monthlyProfit}/revision', [AdminActionsController::class, 'reviseMonthlyProfit'])->name('monthly-profits.revise');
    Route::get('/settlements', [SidebarPageController::class, 'settlements'])->name('settlements');
    Route::post('/settlements', [AdminActionsController::class, 'storeSettlement'])->name('settlements.store');
    Route::post('/settlements/{settlement}/approve', [AdminActionsController::class, 'approveSettlement'])->name('settlements.approve');
    Route::post('/settlements/{settlement}/payments', [AdminActionsController::class, 'recordSettlementPayment'])->name('settlements.payment');
    Route::post('/settlements/{settlement}/revision', [AdminActionsController::class, 'reviseSettlement'])->name('settlements.revise');
    Route::get('/funds', [SidebarPageController::class, 'funds'])->name('funds');
    Route::post('/funds', [AdminActionsController::class, 'storeFund'])->name('funds.store');
    Route::post('/funds/{fund}/transactions', [AdminActionsController::class, 'storeFundTransaction'])->name('funds.transactions.store');
    Route::get('/depreciation', [SidebarPageController::class, 'depreciation'])->name('depreciation');
    Route::get('/reports', [SidebarPageController::class, 'reports'])->name('reports');
    Route::get('/reports/{report}/export/excel', [ReportController::class, 'excel'])->name('reports.export.excel');
    Route::get('/reports/{report}/export/pdf', [ReportController::class, 'pdf'])->name('reports.export.pdf');
    Route::get('/reports/{report}', [ReportController::class, 'index'])->name('reports.show');
    Route::get('/notifications', [SidebarPageController::class, 'notifications'])->name('notifications');
    Route::get('/distribution-rules', [SidebarPageController::class, 'distributionRules'])->name('distribution-rules');
    Route::post('/distribution-rules', [AdminActionsController::class, 'storeDistributionRule'])->name('distribution-rules.store');
    Route::patch('/distribution-rules/{distributionRule}', [AdminActionsController::class, 'updateDistributionRule'])->name('distribution-rules.update');
    Route::get('/settings', [SidebarPageController::class, 'settings'])->name('settings');
    Route::get('/audit-logs', [SidebarPageController::class, 'auditLogs'])->name('audit-logs');
    Route::get('/audit-logs/{auditLog}', [SidebarPageController::class, 'auditLogDetails'])->name('audit-logs.show');
});

Route::get('/', function () {
    return view('welcome');
});
