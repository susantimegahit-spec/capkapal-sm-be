<?php

namespace App\Modules\Reward\Services;

use Illuminate\Support\Facades\DB;
use App\Modules\Auth\Models\User;
use App\Modules\Reward\Models\PointItem;
use App\Modules\Reward\Models\Redemption;
use App\Modules\Reward\Models\PointTransaction;
use App\Modules\Reward\Models\UserVoucher;
use Exception;

class PointRedemptionService
{
    /**
     * Proses penukaran poin
     */
    public function redeem(string $userId, int $itemId, ?string $shippingAddress = null)
    {
        return DB::transaction(function () use ($userId, $itemId, $shippingAddress) {
            // 1. Lock user row untuk mencegah race condition (Pessimistic Locking)
            $user = User::where('id', $userId)->lockForUpdate()->firstOrFail();

            // 2. Lock item row untuk cek stok
            $item = PointItem::where('id', $itemId)->lockForUpdate()->firstOrFail();

            // 3. Validasi Poin & Stok
            if ($user->points < $item->point_cost) {
                throw new Exception("Poin tidak mencukupi.");
            }
            if ($item->stock <= 0) {
                throw new Exception("Stok item habis.");
            }

            // 4. Potong poin & stok
            $user->points -= $item->point_cost;
            $user->save();

            $item->stock -= 1;
            $item->save();

            // 5. Buat Record Redemption
            $redemption = Redemption::create([
                'user_id' => $user->id,
                'point_item_id' => $item->id,
                'points_used' => $item->point_cost,
                'type' => $item->type,
                'status' => $item->type === 'VOUCHER' ? 'COMPLETED' : 'PENDING',
                'shipping_address' => $item->type === 'PHYSICAL' ? $shippingAddress : null,
            ]);

            // 6. Catat Mutasi (Point Transaction)
            PointTransaction::create([
                'user_id' => $user->id,
                'amount' => -$item->point_cost,
                'type' => 'REDEEM',
                'description' => "Penukaran hadiah: " . $item->name,
                'reference_type' => Redemption::class,
                'reference_id' => $redemption->id,
            ]);

            // 7. Jika Voucher, langsung generate ke tabel user_vouchers
            if ($item->type === 'VOUCHER') {
                UserVoucher::create([
                    'user_id' => $user->id,
                    'redemption_id' => $redemption->id,
                    'code' => $this->generateVoucherCode(),
                    'discount_type' => $item->discount_type ?? 'FIXED',
                    'discount_value' => $item->discount_value ?? 0,
                    'status' => 'ACTIVE',
                    'valid_until' => now()->addDays($item->expiry_days ?? 30),
                ]);
            }

            return $redemption;
        });
    }

    /**
     * Proses Reject oleh Admin (Pengembalian Poin)
     */
    public function rejectRedemption(int $redemptionId)
    {
        return DB::transaction(function () use ($redemptionId) {
            $redemption = Redemption::where('id', $redemptionId)->lockForUpdate()->firstOrFail();

            if ($redemption->status !== 'PENDING') {
                throw new Exception("Hanya status PENDING yang bisa di-reject.");
            }

            // Update status
            $redemption->status = 'REJECTED';
            $redemption->save();

            // Kembalikan stok
            $item = PointItem::find($redemption->point_item_id);
            $item->stock += 1;
            $item->save();

            // Kembalikan poin user
            $user = User::where('id', $redemption->user_id)->lockForUpdate()->firstOrFail();
            $user->points += $redemption->points_used;
            $user->save();

            // Catat Refund Mutasi
            PointTransaction::create([
                'user_id' => $user->id,
                'amount' => $redemption->points_used,
                'type' => 'REFUND',
                'description' => "Pengembalian poin (Reject): " . $item->name,
                'reference_type' => Redemption::class,
                'reference_id' => $redemption->id,
            ]);

            return $redemption;
        });
    }

    private function generateVoucherCode()
    {
        return 'VCH-' . strtoupper(uniqid());
    }
}
