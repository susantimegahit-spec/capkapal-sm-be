<?php

use App\Modules\Career\Controllers\{CareerController, CareerApplicationController};
use Illuminate\Support\Facades\Route;

// ── Public routes ─────────────────────────────────────────────────────────────
Route::prefix('careers')->group(function () {
    Route::get('/',              [CareerController::class, 'index']);
    Route::get('/{slug}',        [CareerController::class, 'show']);
    Route::post('/{slug}/apply', [CareerApplicationController::class, 'store']);
});

// ── Admin routes ──────────────────────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:admin|editor'])->prefix('admin')->group(function () {
    Route::prefix('careers')->group(function () {
        Route::get('/',                          [CareerController::class, 'adminIndex']);
        Route::post('/',                         [CareerController::class, 'store']);
        Route::get('/{career}',                  [CareerController::class, 'adminShow'])->where('career', '[0-9a-fA-F\-]{36}');
        Route::put('/{career}',                  [CareerController::class, 'update']);
        Route::delete('/{career}',               [CareerController::class, 'destroy']);
        Route::patch('/{career}/status',         [CareerController::class, 'updateStatus']);

        // Applications management
        Route::get('/{career}/applications',                 [CareerApplicationController::class, 'index']);
        Route::get('/applications/{application}',            [CareerApplicationController::class, 'show']);
        Route::patch('/applications/{application}/status',   [CareerApplicationController::class, 'updateStatus']);
    });
});
