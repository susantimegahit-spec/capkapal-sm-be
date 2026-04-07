<?php

namespace App\Modules\Auth\Controllers;

use App\Core\Traits\ApiResponseTrait;
use App\Modules\Auth\DataTransferObjects\RegisterData;
use App\Modules\Auth\Requests\{LoginRequest, RegisterRequest, UpdateProfileRequest};
use App\Modules\Auth\Resources\UserResource;
use App\Modules\Auth\Services\AuthService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controller;

class AuthController extends Controller
{
  use ApiResponseTrait;

  public function __construct(
    private readonly AuthService $authService
  ) {}

  // ── POST /api/v1/auth/register ────────────────────────────────────────────
  public function register(RegisterRequest $request): JsonResponse
  {
    $data = RegisterData::from($request->validated());

    ['user' => $user, 'tokenData' => $tokenData] = $this->authService->register($data);

    return $this->created([
      'user'       => new UserResource($user),
      'token'      => $tokenData['token'],
      'token_type' => $tokenData['token_type'],
      'expires_at' => $tokenData['expires_at'],
    ], 'Registrasi berhasil.');
  }

  // ── POST /api/v1/auth/login ───────────────────────────────────────────────
  public function login(LoginRequest $request): JsonResponse
  {
    ['user' => $user, 'tokenData' => $tokenData] = $this->authService->login(
      email: $request->email,
      password: $request->password,
      deviceName: $request->device_name,
    );

    return $this->success([
      'user'       => new UserResource($user),
      'token'      => $tokenData['token'],
      'token_type' => $tokenData['token_type'],
      'expires_at' => $tokenData['expires_at'],
    ], 'Login berhasil.');
  }

  // ── POST /api/v1/auth/logout ──────────────────────────────────────────────
  public function logout(Request $request): JsonResponse
  {
    $this->authService->logout($request->user());

    return $this->success(null, 'Logout berhasil.');
  }

  // ── POST /api/v1/auth/logout-all ─────────────────────────────────────────
  public function logoutAll(Request $request): JsonResponse
  {
    $this->authService->logoutAll($request->user());

    return $this->success(null, 'Semua sesi berhasil diakhiri.');
  }

  // ── POST /api/v1/auth/refresh ─────────────────────────────────────────────
  public function refresh(Request $request): JsonResponse
  {
    ['tokenData' => $tokenData] = $this->authService->refreshToken($request->user());

    return $this->success([
      'token'      => $tokenData['token'],
      'token_type' => $tokenData['token_type'],
      'expires_at' => $tokenData['expires_at'],
    ], 'Token diperbarui.');
  }

  // ── GET /api/v1/auth/me ───────────────────────────────────────────────────
  public function me(Request $request): JsonResponse
  {
    return $this->success(
      new UserResource($request->user()->load('profile'))
    );
  }

  // ── GET /api/v1/user/profile ──────────────────────────────────────────────
  public function profile(Request $request): JsonResponse
  {
    return $this->success(
      new UserResource($request->user()->load('profile'))
    );
  }

  // ── PUT /api/v1/user/profile ──────────────────────────────────────────────
  public function updateProfile(UpdateProfileRequest $request): JsonResponse
  {
    $user = $this->authService->updateProfile(
      $request->user(),
      $request->validated()
    );

    return $this->success(
      new UserResource($user),
      'Profil berhasil diperbarui.'
    );
  }

  // ── POST /api/v1/user/avatar ──────────────────────────────────────────────
  public function uploadAvatar(Request $request): JsonResponse
  {
    $request->validate([
      'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
    ]);

    $path = $request->file('avatar')->store(
      path: 'avatars/' . $request->user()->id,
      options: 's3'
    );

    $user = $this->authService->updateProfile(
      $request->user(),
      ['avatar' => $path]
    );

    return $this->success(
      ['avatar_url' => $user->avatar_url],
      'Avatar berhasil diperbarui.'
    );
  }

  // ── PUT /api/v1/user/password ─────────────────────────────────────────────
  public function changePassword(Request $request): JsonResponse
  {
    $request->validate([
      'current_password' => ['required', 'string'],
      'password'         => ['required', 'string', 'min:8', 'confirmed'],
    ]);

    $this->authService->changePassword(
      user: $request->user(),
      current: $request->current_password,
      newPassword: $request->password,
    );

    return $this->success(null, 'Password berhasil diubah.');
  }

  // ── POST /api/v1/auth/forgot-password ─────────────────────────────────────
  public function forgotPassword(Request $request): JsonResponse
  {
    $request->validate([
      'email' => ['required', 'email'],
    ]);

    $this->authService->forgotPassword($request->email);

    return $this->success(null, 'Link reset password telah dikirim ke email Anda.');
  }

  // ── POST /api/v1/auth/reset-password ──────────────────────────────────────
  public function resetPassword(Request $request): JsonResponse
  {
    $request->validate([
      'token'    => ['required', 'string'],
      'email'    => ['required', 'email'],
      'password' => ['required', 'string', 'min:8', 'confirmed'],
    ]);

    $this->authService->resetPassword(
      $request->token,
      $request->email,
      $request->password
    );

    return $this->success(null, 'Password berhasil direset. Silakan login.');
  }
}
