<?php

use App\Modules\Auth\Controllers\AuthController;
use App\Modules\Auth\Controllers\UserAddressController;
use Illuminate\Support\Facades\Route;

// Public auth routes
Route::prefix('auth')->group(function () {
  Route::post('register',        [AuthController::class, 'register']);
  Route::post('login',           [AuthController::class, 'login']);
  Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
  Route::post('reset-password',  [AuthController::class, 'resetPassword']);
  Route::get('verify-email/{token}', [AuthController::class, 'verifyEmail']);
});

// Protected auth routes
Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
  Route::get('me',      [AuthController::class, 'me']);
  Route::post('logout', [AuthController::class, 'logout']);
  Route::post('refresh', [AuthController::class, 'refresh']);
});

// User profile
Route::middleware('auth:sanctum')->prefix('user')->group(function () {
  Route::get('profile',      [AuthController::class, 'profile']);
  Route::put('profile',      [AuthController::class, 'updateProfile']);
  Route::post('avatar',      [AuthController::class, 'uploadAvatar']);
  Route::put('password',     [AuthController::class, 'changePassword']);
});

Route::middleware('auth:sanctum')->prefix('addresses')->group(function () {
  Route::get('/',          [UserAddressController::class, 'index']);
  Route::post('/',         [UserAddressController::class, 'store']);
  Route::put('/{address}', [UserAddressController::class, 'update']);
  Route::delete('/{address}', [UserAddressController::class, 'destroy']);
});