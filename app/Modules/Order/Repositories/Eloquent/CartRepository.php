<?php

namespace App\Modules\Order\Repositories\Eloquent;

use App\Modules\Order\Models\{Cart};
use Illuminate\Support\Facades\DB;

class CartRepository
{
    public function getOrCreateForUser(string $userId): Cart
    {
        return Cart::firstOrCreate(
            ['user_id' => $userId],
            ['user_id' => $userId]
        )->load('items.variant.product.primaryImage');
    }

    public function getOrCreateForSession(string $sessionId): Cart
    {
        return Cart::firstOrCreate(
            ['session_id' => $sessionId],
            [
                'session_id' => $sessionId,
                'expires_at' => now()->addDays(7),
            ]
        )->load('items.variant.product.primaryImage');
    }

    /**
     * Merge guest cart ke user cart saat login.
     */
    public function mergeGuestCart(string $sessionId, string $userId): Cart
    {
        return DB::transaction(function () use ($sessionId, $userId) {
            $guestCart = Cart::where('session_id', $sessionId)->with('items')->first();
            $userCart  = $this->getOrCreateForUser($userId);

            if (! $guestCart || $guestCart->isEmpty()) {
                return $userCart;
            }

            foreach ($guestCart->items as $guestItem) {
                $existing = $userCart->items()
                    ->where('product_variant_id', $guestItem->product_variant_id)
                    ->first();

                if ($existing) {
                    $existing->increment('quantity', $guestItem->quantity);
                } else {
                    $userCart->items()->create([
                        'product_variant_id' => $guestItem->product_variant_id,
                        'quantity'           => $guestItem->quantity,
                        'unit_price'         => $guestItem->unit_price,
                    ]);
                }
            }

            $guestCart->delete();

            return $userCart->fresh('items.variant.product.primaryImage');
        });
    }

    public function clearCart(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->update([
            'coupon_code'     => null,
            'coupon_discount' => 0,
            'points_used'     => 0,
            'points_discount' => 0,
        ]);
    }

    public function removeItems(Cart $cart, array $itemIds): void
    {
        $cart->items()->whereIn('id', $itemIds)->delete();

        // Check if cart is empty after removal
        if ($cart->items()->count() === 0) {
            $cart->update([
                'coupon_code'     => null,
                'coupon_discount' => 0,
                'points_used'     => 0,
                'points_discount' => 0,
            ]);
        }
    }
}
