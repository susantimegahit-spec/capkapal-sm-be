<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Models\UserAddress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UserAddressService
{
  public function getUserAddresses(string $userId): Collection
  {
    return UserAddress::where('user_id', $userId)
      ->orderByDesc('is_primary')
      ->latest()
      ->get();
  }

  public function createAddress(string $userId, array $data): UserAddress
  {
    return DB::transaction(function () use ($userId, $data) {
      $isPrimary = $data['is_primary'] ?? false;

      // Jika user belum punya alamat sama sekali, paksa jadi primary
      if (!UserAddress::where('user_id', $userId)->exists()) {
        $isPrimary = true;
      }

      if ($isPrimary) {
        $this->removeOtherPrimaryAddresses($userId);
      }

      return UserAddress::create([
        ...$data,
        'user_id' => $userId,
        'is_primary' => $isPrimary,
      ]);
    });
  }

  public function updateAddress(string $userId, string $addressId, array $data): UserAddress
  {
    return DB::transaction(function () use ($userId, $addressId, $data) {
      $address = UserAddress::where('user_id', $userId)->findOrFail($addressId);

      $isPrimary = $data['is_primary'] ?? $address->is_primary;

      if ($isPrimary && !$address->is_primary) {
        $this->removeOtherPrimaryAddresses($userId);
      }

      $address->update([
        ...$data,
        'is_primary' => $isPrimary,
      ]);

      return $address;
    });
  }

  public function deleteAddress(string $userId, string $addressId): void
  {
    $address = UserAddress::where('user_id', $userId)->findOrFail($addressId);
    $address->delete();

    // Jika yang dihapus adalah primary, jadikan alamat lain (jika ada) sebagai primary
    if ($address->is_primary) {
      $nextAddress = UserAddress::where('user_id', $userId)->first();
      $nextAddress?->update(['is_primary' => true]);
    }
  }

  private function removeOtherPrimaryAddresses(string $userId): void
  {
    UserAddress::where('user_id', $userId)
      ->where('is_primary', true)
      ->update(['is_primary' => false]);
  }
}
