<?php

namespace App\Modules\Auth\Repositories\Eloquent;

use App\Modules\Auth\Models\{User, UserProfile};
use App\Modules\Auth\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\{Collection, Model};
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserRepository implements UserRepositoryInterface
{
  public function __construct(protected User $model) {}

  public function all(array $filters = []): Collection
  {
    return $this->model->with('profile')->get();
  }

  public function paginate(int $perPage = 20, array $filters = []): LengthAwarePaginator
  {
    return $this->model
      ->with('profile')
      ->when($filters['status'] ?? null, fn($q, $v) => $q->where('status', $v))
      ->when($filters['user_type'] ?? null, fn($q, $v) => $q->where('user_type', $v))
      ->when(
        $filters['search'] ?? null,
        fn($q, $v) =>
        $q->where(
          fn($s) =>
          $s->where('name', 'ilike', "%{$v}%")
            ->orWhere('email', 'ilike', "%{$v}%")
            ->orWhere('phone', 'ilike', "%{$v}%")
        )
      )
      ->latest()
      ->paginate($perPage);
  }

  public function findById(string|int $id): ?User
  {
    return $this->model->with('profile')->find($id);
  }

  public function findOrFail(string|int $id): User
  {
    return $this->model->with('profile')->findOrFail($id);
  }

  public function findByEmail(string $email): ?User
  {
    return $this->model->where('email', $email)->first();
  }

  public function findByPhone(string $phone): ?User
  {
    return $this->model->where('phone', $phone)->first();
  }

  public function findByReferralCode(string $code): ?User
  {
    return $this->model->where('referral_code', $code)->first();
  }

  public function existsByEmail(string $email): bool
  {
    return $this->model->where('email', $email)->exists();
  }

  public function create(array $data): User
  {
    return $this->model->create($data);
  }

  public function createWithProfile(array $userData, array $profileData = []): User
  {
    return DB::transaction(function () use ($userData, $profileData) {
      $userData['referral_code'] = $this->generateUniqueReferralCode();

      $user = $this->model->create($userData);

      if (! empty($profileData)) {
        $user->profile()->create($profileData);
      } else {
        // Always create empty profile
        $user->profile()->create(['user_id' => $user->id]);
      }

      $user->assignRole('user');

      return $user->load('profile');
    });
  }

  public function update(string|int $id, array $data): User
  {
    $user = $this->findOrFail($id);
    $user->update($data);

    return $user->fresh('profile');
  }

  public function updateProfile(string $userId, array $profileData): User
  {
    $user = $this->findOrFail($userId);

    $user->profile()->updateOrCreate(
      ['user_id' => $user->id],
      $profileData
    );

    return $user->load('profile');
  }

  public function delete(string|int $id): bool
  {
    return $this->findOrFail($id)->delete();
  }

  // ── Private ───────────────────────────────────────────────────────────────

  private function generateUniqueReferralCode(): string
  {
    do {
      $code = strtoupper(Str::random(8));
    } while ($this->model->where('referral_code', $code)->exists());

    return $code;
  }
}
