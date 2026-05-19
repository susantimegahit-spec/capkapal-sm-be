<?php

namespace App\Modules\Order\Services;

use App\Modules\Order\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RewardService
{
    // 1 poin = Rp 10
    public const POINTS_VALUE = 10;
    // 1 poin per Rp 10.000 pembelian
    private const EARN_RATE    = 10000;

    public function calculatePointsValue(string $userId, int $pointsToUse): float
    {
        $balance = DB::table('reward_points')
            ->where('user_id', $userId)
            ->value('balance') ?? 0;

        if ($pointsToUse > $balance) {
            throw ValidationException::withMessages([
                'points_used' => ["Poin tidak cukup. Saldo Anda: {$balance} poin."],
            ]);
        }

        return $pointsToUse * self::POINTS_VALUE;
    }

    public function redeem(string $userId, int $points, string $orderId): void
    {
        DB::transaction(function () use ($userId, $points, $orderId) {
            $rp = DB::table('reward_points')
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (! $rp || $rp->balance < $points) {
                throw ValidationException::withMessages([
                    'points_used' => ['Poin tidak cukup.'],
                ]);
            }

            $newBalance = $rp->balance - $points;

            DB::table('reward_points')
                ->where('user_id', $userId)
                ->update([
                    'balance'           => $newBalance,
                    'lifetime_redeemed' => DB::raw("lifetime_redeemed + {$points}"),
                    'updated_at'        => now(),
                ]);

            DB::table('reward_transactions')->insert([
                'id'            => \Str::uuid(),
                'user_id'       => $userId,
                'type'          => 'redeem',
                'points'        => -$points,
                'balance_after' => $newBalance,
                'ref_type'      => 'order',
                'ref_id'        => $orderId,
                'description'   => 'Penukaran poin untuk diskon',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        });
    }

    public function earnFromOrder(Order $order): void
    {
        $points = (int) floor($order->total / self::EARN_RATE);

        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $points) {
            $rp = DB::table('reward_points')
                ->where('user_id', $order->user_id)
                ->lockForUpdate()
                ->first();

            if (! $rp) {
                DB::table('reward_points')->insert([
                    'id'               => \Str::uuid(),
                    'user_id'          => $order->user_id,
                    'balance'          => $points,
                    'lifetime_earned'  => $points,
                    'lifetime_redeemed'=> 0,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]);
                $newBalance = $points;
            } else {
                $newBalance = $rp->balance + $points;
                DB::table('reward_points')
                    ->where('user_id', $order->user_id)
                    ->update([
                        'balance'         => $newBalance,
                        'lifetime_earned' => DB::raw("lifetime_earned + {$points}"),
                        'updated_at'      => now(),
                    ]);
            }

            DB::table('reward_transactions')->insert([
                'id'            => \Str::uuid(),
                'user_id'       => $order->user_id,
                'type'          => 'earn',
                'points'        => $points,
                'balance_after' => $newBalance,
                'ref_type'      => 'order',
                'ref_id'        => $order->id,
                'description'   => "Poin dari order #{$order->order_number}",
                'expires_at'    => now()->addYear(),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // Update points_earned di order
            $order->update(['points_earned' => $points]);
        });

        // Cek naik tier di background
        \App\Jobs\CheckRewardTierUpgrade::dispatch($order->user_id)->onQueue('low');
    }

    public function refundPoints(string $userId, int $points, string $orderId): void
    {
        DB::transaction(function () use ($userId, $points, $orderId) {
            $rp         = DB::table('reward_points')->where('user_id', $userId)->lockForUpdate()->first();
            $newBalance = ($rp->balance ?? 0) + $points;

            DB::table('reward_points')->where('user_id', $userId)
                ->update(['balance' => $newBalance, 'updated_at' => now()]);

            DB::table('reward_transactions')->insert([
                'id'            => \Str::uuid(),
                'user_id'       => $userId,
                'type'          => 'refund',
                'points'        => $points,
                'balance_after' => $newBalance,
                'ref_type'      => 'order',
                'ref_id'        => $orderId,
                'description'   => 'Pengembalian poin karena order dibatalkan',
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        });
    }
}
