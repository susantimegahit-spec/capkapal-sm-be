<?php

use App\Modules\Admin\Controllers\{
    AuditLogController,
    DashboardController,
    InventoryController,
    OrderManagementController,
    ReportController,
    UserManagementController,
};
use Illuminate\Support\Facades\Route;

// Semua route di sini sudah dibungkus:
// middleware(['auth:sanctum', 'role:admin|editor'])
// prefix('admin')
// di routes/api.php

// ── Dashboard ─────────────────────────────────────────────────────────────────
Route::prefix('dashboard')->group(function () {
    Route::get('/',                 [DashboardController::class, 'index']);
    Route::get('/revenue-chart',    [DashboardController::class, 'revenueChart']);
    Route::get('/top-products',     [DashboardController::class, 'topProducts']);
    Route::get('/user-distribution',[DashboardController::class, 'userDistribution']);
});

// ── Orders ────────────────────────────────────────────────────────────────────
Route::prefix('orders')->group(function () {
    Route::get('/',                         [OrderManagementController::class, 'index']);
    Route::get('/{orderNumber}',            [OrderManagementController::class, 'show']);
    Route::patch('/{orderId}/status',       [OrderManagementController::class, 'updateStatus']);
    Route::post('/{orderId}/notes',         [OrderManagementController::class, 'addNote']);
});

// ── Reports ───────────────────────────────────────────────────────────────────
Route::prefix('reports')->group(function () {
    Route::get('/revenue',          [ReportController::class, 'revenue']);
    Route::get('/products',         [ReportController::class, 'products']);
    Route::get('/users',            [ReportController::class, 'users']);
    Route::get('/revenue/export',   [ReportController::class, 'exportRevenueCsv']);
    Route::post('/export',          [ReportController::class, 'queueExport']);
    Route::get('/export/{exportId}',[ReportController::class, 'checkExport']);
});

// ── Inventory ───────────────────────────────────────────────────────────────────
Route::prefix('inventory')->group(function () {
    // Dapat diakses admin & editor (read only)
    Route::get('/summary',      [InventoryController::class, 'summary']);
    Route::get('/low-stock',    [InventoryController::class, 'lowStock']);
    Route::get('/out-of-stock', [InventoryController::class, 'outOfStock']);

    // Hanya admin yang boleh mengubah stok
    Route::middleware('role:admin')->group(function () {
        Route::patch('/{variantId}/stock', [InventoryController::class, 'adjustStock']);
        Route::post('/bulk-import',        [InventoryController::class, 'bulkImport']);
    });
});

// ── Users ─────────────────────────────────────────────────────────────────────
Route::prefix('users')->group(function () {
    // Dapat diakses admin & editor (read only)
    Route::get('/',         [UserManagementController::class, 'index']);
    Route::get('/{userId}', [UserManagementController::class, 'show']);

    // Hanya admin yang boleh melakukan aksi mutasi user
    Route::middleware('role:admin')->group(function () {
        Route::post('/{userId}/suspend',  [UserManagementController::class, 'suspend']);
        Route::post('/{userId}/activate', [UserManagementController::class, 'activate']);
        Route::patch('/{userId}/role',    [UserManagementController::class, 'updateRole']);
    });
});

// ── Audit Logs (admin only) ───────────────────────────────────────────────────
Route::get('/audit-logs', [AuditLogController::class, 'index'])
    ->middleware('role:admin');
