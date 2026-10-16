<?php

use Illuminate\Support\Facades\Route;

/*
  | Prefix: /v1
  | Semua module routes di-load otomatis dari app/Modules/{module}/routes.php
*/

Route::prefix('v1')->group(function () {

  // ── Public Routes (No Auth) ─────────────────────────────────────
  Route::middleware(['throttle:' . config('app.rate_limit_public', 60)])->group(function () {
    require app_path('Modules/Auth/routes.php');
    require app_path('Modules/Product/routes.php');
    require app_path('Modules/CMS/routes.php');
    require app_path('Modules/Promo/routes.php');
    require app_path('Modules/Forum/routes.php');
    require app_path('Modules/Career/routes.php');
  });

  // ── Webhook Routes (No Auth, HMAC verified internally) ──────────
  require app_path('Modules/Payment/routes.php');

  // ── Authenticated Routes ────────────────────────────────────────
  Route::middleware(['auth:sanctum', 'throttle:' . config('app.rate_limit_auth', 120)])->group(function () {
    require app_path('Modules/Order/routes.php');
    require app_path('Modules/Reward/routes.php');
    require app_path('Modules/Notification/routes.php');
  });

  // ── Admin Routes (Admin/Editor only) ────────────────────────────
  Route::middleware(['auth:sanctum', 'role:admin|editor', 'throttle:' . config('app.rate_limit_admin', 300)])
    ->prefix('admin')
    ->group(function () {
      require app_path('Modules/Admin/routes.php');
    });
});
