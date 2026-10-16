<?php

use App\Modules\Payment\Controllers\{PaymentController, WebhookController};
use App\Modules\Payment\Http\Middleware\VerifyMidtransNotification;
use Illuminate\Support\Facades\Route;

// ── Webhook — public, verifikasi via middleware signature ─────────────────────
Route::post(
    '/webhooks/midtrans',
    [WebhookController::class, 'handleMidtrans']
)->middleware(VerifyMidtransNotification::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/payments/status/{orderId}',       [PaymentController::class, 'status']);
    Route::post('/payments/{orderNumber}/retry',   [PaymentController::class, 'retry']);
    Route::post('/payments/{orderNumber}/upload-proof', [PaymentController::class, 'uploadProof']);

    // Hanya admin yang boleh melakukan sync massal
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::post('/payments/sync-all', [PaymentController::class, 'syncAll']);
    });
});