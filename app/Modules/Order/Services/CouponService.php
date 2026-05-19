<?php

namespace App\Modules\Order\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Order\Models\Coupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * Validasi kupon dan kembalikan jumlah diskon.
     * Lempar ValidationException jika tidak valid.
     */
    public function validate(string $code, float $subtotal, User $user): array
    {
        // Gunakan transaksi + lockForUpdate untuk mencegah race condition
        // saat 2 user menggunakan kupon yang sama secara bersamaan (TOCTOU).
        return DB::transaction(function () use ($code, $subtotal, $user) {
            $coupon = Coupon::where('code', strtoupper($code))
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $coupon) {
                throw ValidationException::withMessages([
                    'coupon_code' => ['Kode kupon tidak valid.'],
                ]);
            }

            // Cek expired
            if ($coupon->expires_at && $coupon->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'coupon_code' => ['Kode kupon sudah kedaluwarsa.'],
                ]);
            }

            // Cek max uses (global)
            if ($coupon->max_uses && $coupon->used_count >= $coupon->max_uses) {
                throw ValidationException::withMessages([
                    'coupon_code' => ['Kupon sudah mencapai batas penggunaan.'],
                ]);
            }

            // Cek per-user limit (nilai 0 = tidak ada batas)
            if ($coupon->max_uses_per_user > 0) {
                $userUsageCount = DB::table('coupon_usages')
                    ->where('coupon_id', $coupon->id)
                    ->where('user_id', $user->id)
                    ->count();

                if ($userUsageCount >= $coupon->max_uses_per_user) {
                    throw ValidationException::withMessages([
                        'coupon_code' => ['Anda sudah menggunakan kupon ini.'],
                    ]);
                }
            }

            // Cek minimum pembelian
            if ($subtotal < $coupon->min_purchase) {
                $min = number_format($coupon->min_purchase, 0, ',', '.');
                throw ValidationException::withMessages([
                    'coupon_code' => ["Minimum pembelian Rp{$min} untuk kupon ini."],
                ]);
            }

            // Cek user_type restriction
            if ($coupon->user_type_restriction !== 'all'
                && $user->user_type->value !== $coupon->user_type_restriction) {
                throw ValidationException::withMessages([
                    'coupon_code' => ['Kupon tidak berlaku untuk tipe akun Anda.'],
                ]);
            }

            // Cek specific user
            if ($coupon->specific_user_id && $coupon->specific_user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'coupon_code' => ['Kode kupon tidak valid.'],
                ]);
            }

            // Hitung diskon
            $discount = $coupon->discount_type === 'percentage'
                ? ($subtotal * $coupon->discount_value / 100)
                : $coupon->discount_value;

            if ($coupon->max_discount) {
                $discount = min($discount, $coupon->max_discount);
            }

            return [
                'coupon'   => $coupon,
                'discount' => round($discount, 2),
            ];
        });
    }


    public function markUsed(string $couponId, string $userId, string $orderId, float $discountApplied): void
    {
        \DB::table('coupon_usages')->insert([
            'id'               => \Str::uuid(),
            'coupon_id'        => $couponId,
            'user_id'          => $userId,
            'order_id'         => $orderId,
            'discount_applied' => $discountApplied,
            'applied_at'       => now(),
        ]);

        Coupon::where('id', $couponId)->increment('used_count');
    }
}
