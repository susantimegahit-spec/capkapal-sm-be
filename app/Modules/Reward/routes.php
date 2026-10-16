<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Reward\Controllers\PointRedemptionController;
use App\Modules\Reward\Controllers\AdminRedemptionController;

// ── User Routes ─────────────────────────────────────────────────────────
Route::prefix('api/reward')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/items', [PointRedemptionController::class, 'getItems']);
    Route::get('/transactions', [PointRedemptionController::class, 'getTransactions']);
    Route::get('/vouchers', [PointRedemptionController::class, 'getVouchers']);
    Route::get('/redemptions/{id}', [PointRedemptionController::class, 'getRedemptionDetail']);
    Route::post('/redeem', [PointRedemptionController::class, 'redeem']);
});

// ── Admin Routes ────────────────────────────────────────────────────────
Route::prefix('api/admin/reward')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // Point Items CRUD
    Route::get('/items', [AdminRedemptionController::class, 'getItems']);
    Route::post('/items', [AdminRedemptionController::class, 'createItem']);
    Route::put('/items/{id}', [AdminRedemptionController::class, 'updateItem']);
    Route::delete('/items/{id}', [AdminRedemptionController::class, 'deleteItem']);

    // Transactions / Verification
    Route::get('/transactions', [AdminRedemptionController::class, 'getTransactions']);
    Route::patch('/redemption/{id}/status', [AdminRedemptionController::class, 'updateStatus']);

    // Summary / Reports
    Route::get('/summary', [AdminRedemptionController::class, 'getSummary']);

    // User History
    Route::get('/users/{id}/history', [AdminRedemptionController::class, 'getUserHistory']);
});
