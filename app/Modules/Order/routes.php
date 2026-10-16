<?php

use App\Modules\Order\Controllers\{CartController, CheckoutController, OrderController};
use Illuminate\Support\Facades\Route;

// Cart
Route::middleware(['auth:sanctum', 'role:user|agen'])->prefix('cart')->group(function () {
    Route::get('/',                     [CartController::class, 'show']);
    Route::post('/items',               [CartController::class, 'addItem']);
    Route::put('/items/{cartItem}',     [CartController::class, 'updateItem']);
    Route::delete('/items/{cartItem}',  [CartController::class, 'removeItem']);
    Route::post('/coupon',              [CartController::class, 'applyCoupon']);
    Route::delete('/coupon',            [CartController::class, 'removeCoupon']);
    Route::get('/shipping-options',     [CartController::class, 'shippingOptions']);
    Route::delete('/',                   [CartController::class, 'clear']);
});

// Checkout
Route::middleware(['auth:sanctum', 'role:user|agen'])->group(function () {
    Route::post('/checkout', [CheckoutController::class, 'process']);
});

// Orders — auth wajib; user hanya bisa akses order miliknya sendiri
Route::middleware(['auth:sanctum', 'role:user|agen'])->prefix('orders')->group(function () {
    Route::get('/',                           [OrderController::class, 'index']);
    Route::get('/{orderNumber}',              [OrderController::class, 'show']);
    Route::post('/{orderNumber}/cancel',      [OrderController::class, 'cancel']);
});

// Catatan: Route updateStatus telah DIPINDAH ke Admin module (OrderManagementController)
// untuk memastikan setiap perubahan status tercatat di audit log.
// Lihat: app/Modules/Admin/routes.php → PATCH /admin/orders/{orderId}/status

