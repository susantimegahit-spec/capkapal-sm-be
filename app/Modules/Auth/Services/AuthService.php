<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\DataTransferObjects\RegisterData;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Enums\UserType;
use App\Modules\Auth\Events\{UserLoggedIn, UserRegistered};
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\{DB, Hash, Password};
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Arr;

class AuthService
{
  public function __construct(
    private readonly UserRepositoryInterface $userRepository,
    private readonly TokenService            $tokenService,
  ) {}

  // ── Register ──────────────────────────────────────────────────────────────

  public function register(RegisterData $data): array
  {
    // Resolve referred_by user if referral code provided
    $referredBy = null;
    if ($data->referral_code) {
      $referredBy = $this->userRepository->findByReferralCode($data->referral_code);
    }

    $userData = [
      'name'        => $data->name,
      'email'       => $data->email,
      'phone'       => $data->phone,
      'password'    => $data->password,
      'user_type'   => UserType::User,
      'status'      => UserStatus::Active,
      'referred_by' => $referredBy?->id,
    ];

    $profileData = array_filter([
      'company_name'         => $data->company_name,
      'distributor_code'     => $data->distributor_code,
      'umkm_registration_no' => $data->umkm_registration_no,
    ]);

    $user = $this->userRepository->createWithProfile($userData, $profileData);

    // Assign default role based on user_type
    $user->assignRole($data->user_type?->defaultRole() ?? UserType::User->defaultRole());

    // Create reward point for user

    // Dispatch event → sends verification email, reward setup, etc.
    event(new UserRegistered($user, referredBy: $referredBy));

    return compact('user');
  }

  // ── Login ─────────────────────────────────────────────────────────────────

  public function login(string $email, string $password, ?string $deviceName = null): array
  {
    $user = $this->userRepository->findByEmail($email);

    if (! $user || ! Hash::check($password, $user->password)) {
      throw ValidationException::withMessages([
        'email' => ['Email atau password salah.'],
      ]);
    }

    if (! $user->isActive()) {
      throw ValidationException::withMessages([
        'email' => ['Akun Anda tidak aktif. Silakan ubungi Admin.'],
      ]);
    }

    if (! $user->hasVerifiedEmail()) {
      throw ValidationException::withMessages([
        'email' => ['Email belum diverifikasi. Silakan cek inbox Anda.'],
      ]);
    }

    event(new UserLoggedIn($user));

    $tokenData = $this->tokenService->issueToken($user);

    return compact('user', 'tokenData');
  }

  // ── Logout ────────────────────────────────────────────────────────────────

  public function logout(User $user): void
  {
    $this->tokenService->revokeCurrentToken($user);
  }

  public function logoutAll(User $user): void
  {
    $this->tokenService->revokeAllTokens($user);
  }

  // ── Refresh Token ─────────────────────────────────────────────────────────

  public function refreshToken(User $user): array
  {
    $tokenData = $this->tokenService->rotateToken($user);

    return compact('user', 'tokenData');
  }

  // ── Forgot Password ───────────────────────────────────────────────────────

  public function forgotPassword(string $email): void
  {
    $status = Password::sendResetLink(['email' => $email]);

    if ($status !== Password::RESET_LINK_SENT) {
      throw ValidationException::withMessages([
        'email' => [__($status)],
      ]);
    }
  }

  // ── Reset Password ────────────────────────────────────────────────────────

  public function resetPassword(
    string $token,
    string $email,
    string $password
  ): void {
    $status = Password::reset(
      credentials: compact('token', 'email', 'password'),
      callback: function (User $user, string $password) {
        $user->forceFill(['password' => $password])->save();
        $this->tokenService->revokeAllTokens($user);
        event(new PasswordReset($user));
      }
    );

    if ($status !== Password::PASSWORD_RESET) {
      throw ValidationException::withMessages([
        'token' => [__($status)],
      ]);
    }
  }

  // ── Verify Email ──────────────────────────────────────────────────────────

  public function verifyEmail(string $token, string $email): void
  {
    $record = DB::table('email_verification_tokens')
      ->where('email', $email)
      ->first();

    if (! $record || ! hash_equals($record->token, hash('sha256', $token))) {
      throw ValidationException::withMessages([
        'token' => ['Token verifikasi tidak valid.'],
      ]);
    }

    // Check if token has expired (default 60 minutes)
    $expireMinutes = config('auth.verification.expire', 60);
    if (now()->diffInMinutes($record->created_at) > $expireMinutes) {
      throw ValidationException::withMessages([
        'token' => ['Token verifikasi sudah kedaluwarsa. Silakan minta link verifikasi baru.'],
      ]);
    }

    $user = $this->userRepository->findByEmail($email);

    if (! $user) {
      throw ValidationException::withMessages([
        'email' => ['Email tidak ditemukan.'],
      ]);
    }

    if ($user->hasVerifiedEmail()) {
      // Already verified, just clean up token
      DB::table('email_verification_tokens')->where('email', $email)->delete();
      return;
    }

    $user->forceFill(['email_verified_at' => now()])->save();

    DB::table('email_verification_tokens')->where('email', $email)->delete();
  }

  // ── Resend Verification Email ─────────────────────────────────────────────

  public function resendVerificationEmail(string $email): void
  {
    $user = $this->userRepository->findByEmail($email);

    if (! $user) {
      // Silent return to prevent email enumeration
      return;
    }

    if ($user->hasVerifiedEmail()) {
      throw ValidationException::withMessages([
        'email' => ['Email sudah terverifikasi. Silakan login.'],
      ]);
    }

    $user->sendEmailVerificationNotification();
  }

  // ── Update Profile ────────────────────────────────────────────────────────

  public function updateProfile(User $user, array $data): User
  {
    $userData    = array_filter(Arr::only($data, ['name', 'phone', 'avatar']));
    $profileData = array_filter(Arr::only($data, [
      'address',
      'city',
      'province',
      'postal_code',
      'company_name',
      'date_of_birth',
      'gender',
    ]));

    if (! empty($userData)) {
      $this->userRepository->update($user->id, $userData);
    }

    if (! empty($profileData)) {
      $this->userRepository->updateProfile($user->id, $profileData);
    }

    return $this->userRepository->findOrFail($user->id);
  }

  // ── Change Password ───────────────────────────────────────────────────────

  public function changePassword(User $user, string $current, string $newPassword): void
  {
    if (! Hash::check($current, $user->password)) {
      throw ValidationException::withMessages([
        'current_password' => ['Password lama tidak sesuai.'],
      ]);
    }

    $this->userRepository->update($user->id, ['password' => $newPassword]);

    // Revoke all other tokens (keep current session)
    $currentTokenId = $user->currentAccessToken()->id;
    $user->tokens()->where('id', '!=', $currentTokenId)->delete();
  }
}
