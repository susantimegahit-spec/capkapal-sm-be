<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\NewAccessToken;

class TokenService
{
  private const TOKEN_NAME     = 'api-token';
  private const BLACKLIST_TTL  = 60 * 24 * 7; // 7 days in minutes

    public function issueToken(User $user, array $abilities = ['*']): array
    {
        // Revoke old tokens if exceeding limit (optional: keep last 5)
        $this->pruneOldTokens($user);

        // Ambil nilai dari config (dalam menit)
        $expiration = config('sanctum.expiration');

        $token = $user->createToken(
            name: self::TOKEN_NAME,
            abilities: $abilities,
            // Jika null, jangan set expiresAt. Jika ada, tambahkan dalam MENIT.
            expiresAt: $expiration ? now()->addMinutes($expiration) : null,
        );

        return [
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $token->accessToken->expires_at?->toISOString(),
        ];
    }

  public function revokeCurrentToken(User $user): void
  {
    $token = $user->currentAccessToken();

    // Add to blacklist cache (belt-and-suspenders)
    Cache::put(
      key: $this->blacklistKey($token->id),
      value: true,
      ttl: now()->addMinutes(self::BLACKLIST_TTL),
    );

    $token->delete();
  }

  public function revokeAllTokens(User $user): void
  {
    $user->tokens()->each(function ($token) {
      Cache::put($this->blacklistKey($token->id), true, now()->addMinutes(self::BLACKLIST_TTL));
    });

    $user->tokens()->delete();
  }

  public function rotateToken(User $user): array
  {
    $this->revokeCurrentToken($user);

    return $this->issueToken($user);
  }

  public function isBlacklisted(int|string $tokenId): bool
  {
    return Cache::has($this->blacklistKey($tokenId));
  }

  // ── Private ───────────────────────────────────────────────────────────────

  private function blacklistKey(int|string $tokenId): string
  {
    return "token_blacklist:{$tokenId}";
  }

  private function pruneOldTokens(User $user, int $keep = 5): void
  {
    $tokenCount = $user->tokens()->count();

    if ($tokenCount >= $keep) {
      $user->tokens()
        ->oldest()
        ->limit($tokenCount - $keep + 1)
        ->delete();
    }
  }
}
