<?php

use App\Http\Controllers\Admin\SidebarPageController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminWebAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/login', [AdminWebAuthController::class, 'login'])->name('admin.login');
Route::post('/admin/login', [AdminWebAuthController::class, 'authenticate'])->name('admin.login.submit');
Route::post('/admin/logout', [AdminWebAuthController::class, 'logout'])->middleware('ensure.web.admin')->name('admin.logout');

Route::middleware('ensure.web.admin')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', AdminDashboardController::class)->name('dashboard');
    Route::get('/participants', [SidebarPageController::class, 'participants'])->name('participants');
    Route::get('/investments', [SidebarPageController::class, 'investments'])->name('investments');
    Route::get('/capital', [SidebarPageController::class, 'capital'])->name('capital');
    Route::get('/monthly-profits', [SidebarPageController::class, 'monthlyProfits'])->name('monthly-profits');
    Route::get('/settlements', [SidebarPageController::class, 'settlements'])->name('settlements');
    Route::get('/funds', [SidebarPageController::class, 'funds'])->name('funds');
    Route::get('/depreciation', [SidebarPageController::class, 'depreciation'])->name('depreciation');
    Route::get('/reports', [SidebarPageController::class, 'reports'])->name('reports');
    Route::get('/notifications', [SidebarPageController::class, 'notifications'])->name('notifications');
    Route::get('/distribution-rules', [SidebarPageController::class, 'distributionRules'])->name('distribution-rules');
    Route::get('/settings', [SidebarPageController::class, 'settings'])->name('settings');
    Route::get('/audit-logs', [SidebarPageController::class, 'auditLogs'])->name('audit-logs');
});

Route::get('/', function () {
    return view('welcome');
});
