<?php

use App\Modules\Order\Controllers\{CartController, CheckoutController, OrderController};
use Illuminate\Support\Facades\Route;

// ── Semua protected by auth:sanctum (di-set di routes/api.php) ───────────────

// Cart
Route::prefix('cart')->group(function () {
    Route::get('/',                     [CartController::class, 'show']);
    Route::post('/items',               [CartController::class, 'addItem']);
    Route::put('/items/{cartItem}',     [CartController::class, 'updateItem']);
    Route::delete('/items/{cartItem}',  [CartController::class, 'removeItem']);
    Route::post('/coupon',              [CartController::class, 'applyCoupon']);
    Route::delete('/coupon',            [CartController::class, 'removeCoupon']);
    Route::get('/shipping-options',     [CartController::class, 'shippingOptions']);
});

// Checkout
Route::post('/checkout', [CheckoutController::class, 'process']);

// Orders
Route::prefix('orders')->group(function () {
    Route::get('/',                           [OrderController::class, 'index']);
    Route::get('/{orderNumber}',              [OrderController::class, 'show']);
    Route::post('/{orderNumber}/cancel',      [OrderController::class, 'cancel']);
});

// Admin routes (dibungkus middleware role:admin di api.php)
Route::patch('/orders/{orderId}/status', [OrderController::class, 'updateStatus']);
