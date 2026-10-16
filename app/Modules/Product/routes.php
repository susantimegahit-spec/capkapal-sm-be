<?php

use App\Modules\Product\Controllers\{CategoryController, ProductController, ReviewController};
use Illuminate\Support\Facades\Route;

// ── Public routes ─────────────────────────────────────────────────────────────
Route::prefix('products')->group(function () {
    Route::get('/',                  [ProductController::class, 'index']);
    Route::get('/featured',          [ProductController::class, 'featured']);
    Route::get('/search/suggest',    [ProductController::class, 'suggest']);
    Route::get('/{slug}',            [ProductController::class, 'show']);
    Route::get('/{slug}/reviews',    [ReviewController::class, 'index']);
});

Route::prefix('categories')->group(function () {
    Route::get('/',        [CategoryController::class, 'index']);
    Route::get('/{slug}',  [CategoryController::class, 'show']);
});

// ── Auth required ─────────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/products/{slug}/reviews', [ReviewController::class, 'store']);
});

// ── Admin routes ──────────────────────────────────────────────────────────────
// Operasi konten produk: admin dan editor boleh
Route::middleware(['auth:sanctum', 'role:admin|editor'])->prefix('admin')->group(function () {
    Route::prefix('products')->group(function () {
        Route::get('/',                          [ProductController::class, 'adminIndex']);
        Route::post('/',                         [ProductController::class, 'store']);
        Route::put('/{product}',                 [ProductController::class, 'update']);
        // Route::delete('/{product}',              [ProductController::class, 'destroy']);
        Route::patch('/{product}/update-status', [ProductController::class, 'updateStatus']);
        Route::post('/{product}/images',         [ProductController::class, 'uploadImage']);
        Route::delete('/images/{image}',         [ProductController::class, 'deleteImage']);
    });

    Route::prefix('categories')->group(function () {
        Route::post('/',       [CategoryController::class, 'store']);
        Route::put('/{id}',    [CategoryController::class, 'update']);
    });
});

// Operasi stok: hanya admin yang boleh (operasi kritis inventory)
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::patch('/products/variants/{variant}/stock', [ProductController::class, 'updateStock']);
});
