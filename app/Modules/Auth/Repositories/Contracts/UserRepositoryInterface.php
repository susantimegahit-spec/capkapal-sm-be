<?php

namespace App\Modules\Auth\Repositories\Contracts;

use App\Core\Contracts\RepositoryInterface;
use App\Modules\Auth\Models\User;

interface UserRepositoryInterface extends RepositoryInterface
{
  public function findByEmail(string $email): ?User;
  public function findByPhone(string $phone): ?User;
  public function findByReferralCode(string $code): ?User;
  public function createWithProfile(array $userData, array $profileData = []): User;
  public function updateProfile(string $userId, array $profileData): User;
  public function existsByEmail(string $email): bool;
}
