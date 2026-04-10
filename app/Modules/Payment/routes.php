<?php

use App\Modules\Payment\Controllers\{PaymentController, WebhookController};
use App\Modules\Payment\Http\Middleware\VerifyXenditWebhook;
use Illuminate\Support\Facades\Route;

// ── Webhook — public, verifikasi via middleware HMAC ─────────────────────────
Route::post(
    '/webhooks/xendit',
    [WebhookController::class, 'handleXendit']
)->middleware(VerifyXenditWebhook::class);

// ── Protected ─────────────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/payments/status/{orderId}',       [PaymentController::class, 'status']);
    Route::post('/payments/{orderNumber}/retry',   [PaymentController::class, 'retry']);
});
