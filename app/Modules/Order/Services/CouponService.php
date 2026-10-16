<?php

namespace App\Modules\Order\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Order\Models\Coupon;
use App\Modules\Reward\Models\UserVoucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * Validasi kupon dan kembalikan jumlah diskon.
     * Mendukung 2 sumber kupon:
     *   1. Tabel `coupons` (kupon promo dari admin)
     *   2. Tabel `user_vouchers` (voucher dari penukaran poin loyalty)
     * Lempar ValidationException jika tidak valid.
     */
    public function validate(string $code, float $subtotal, User $user): array
    {
        // Gunakan transaksi + lockForUpdate untuk mencegah race condition
        // saat 2 user menggunakan kupon yang sama secara bersamaan (TOCTOU).
        return DB::transaction(function () use ($code, $subtotal, $user) {

            // ── 1. Coba cari di tabel coupons (kupon promo admin) ────────
            $coupon = Coupon::where('code', strtoupper($code))
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if ($coupon) {
                return $this->validatePromoCoupon($coupon, $subtotal, $user);
            }

            // ── 2. Fallback: cari di tabel user_vouchers (voucher loyalty) ─
            $voucher = UserVoucher::where('code', strtoupper($code))
                ->lockForUpdate()
                ->first();

            if ($voucher) {
                return $this->validateUserVoucher($voucher, $subtotal, $user);
            }

            // ── Tidak ditemukan di kedua tabel ──────────────────────────────
            throw ValidationException::withMessages([
                'coupon_code' => ['Kode kupon tidak valid.'],
            ]);
        });
    }

    /**
     * Validasi kupon dari tabel `coupons` (promo admin).
     */
    private function validatePromoCoupon(Coupon $coupon, float $subtotal, User $user): array
    {
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
            'source'   => 'coupon', // penanda sumber: kupon promo
        ];
    }

    /**
     * Validasi voucher dari tabel `user_vouchers` (dari penukaran poin loyalty).
     */
    private function validateUserVoucher(UserVoucher $voucher, float $subtotal, User $user): array
    {
        // Voucher harus milik user yang bersangkutan
        if ($voucher->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'coupon_code' => ['Kode kupon tidak valid.'],
            ]);
        }

        // Cek status
        if ($voucher->status !== 'ACTIVE') {
            $msg = $voucher->status === 'USED'
                ? 'Voucher sudah pernah digunakan.'
                : 'Voucher sudah kedaluwarsa.';
            throw ValidationException::withMessages([
                'coupon_code' => [$msg],
            ]);
        }

        // Cek expiry
        if ($voucher->valid_until && $voucher->valid_until->isPast()) {
            // Auto-mark expired
            $voucher->update(['status' => 'EXPIRED']);
            throw ValidationException::withMessages([
                'coupon_code' => ['Voucher sudah kedaluwarsa.'],
            ]);
        }

        // Hitung diskon
        $discount = strtoupper($voucher->discount_type) === 'PERCENTAGE'
            ? ($subtotal * $voucher->discount_value / 100)
            : $voucher->discount_value;

        return [
            'voucher'  => $voucher,
            'discount' => round($discount, 2),
            'source'   => 'user_voucher', // penanda sumber: voucher loyalty
        ];
    }

    /**
     * Tandai kupon promo sebagai terpakai.
     */
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

    /**
     * Tandai voucher loyalty sebagai terpakai.
     */
    public function markVoucherUsed(int $voucherId): void
    {
        UserVoucher::where('id', $voucherId)->update([
            'status'  => 'USED',
            'used_at' => now(),
        ]);
    }
}
