<?php

namespace App\Modules\Order\Services;

use App\Modules\Order\Models\{Cart, CartItem};
use App\Modules\Order\Repositories\Eloquent\CartRepository;
use App\Modules\Product\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function __construct(
        public readonly CartRepository $cartRepository,
        private readonly CouponService $couponService,
    ) {}

    // ── Get Cart ──────────────────────────────────────────────────────────────

    public function getCart(string $userId): Cart
    {
        return $this->cartRepository->getOrCreateForUser($userId);
    }

    // ── Add Item ──────────────────────────────────────────────────────────────

    public function addItem(
        string  $userId,
        string  $variantId,
        int     $quantity,
        ?string $notes    = null,
        string  $userType = 'consumer'
    ): Cart {
        return DB::transaction(function () use ($userId, $variantId, $quantity, $notes, $userType) {
            $variant = ProductVariant::with('product')->lockForUpdate()->findOrFail($variantId);

            $this->assertVariantAvailable($variant, $quantity);

            $cart = $this->cartRepository->getOrCreateForUser($userId);

            $existing = $cart->items()->where('product_variant_id', $variantId)->first();

            if ($existing) {
                $newQty = $existing->quantity + $quantity;
                $this->assertVariantAvailable($variant, $newQty);
                $existing->update(['quantity' => $newQty]);
            } else {
                $cart->items()->create([
                    'product_variant_id' => $variantId,
                    'quantity'           => $quantity,
                    'unit_price'         => $variant->getPriceForUserType($userType),
                    'notes'              => $notes,
                ]);
            }

            // Re-validasi coupon jika ada
            if ($cart->coupon_code) {
                $this->revalidateCoupon($cart);
            }

            return $cart->fresh('items.variant.product.primaryImage');
        });
    }

    // ── Update Quantity ───────────────────────────────────────────────────────

    public function updateItem(string $userId, string $cartItemId, int $quantity): Cart
    {
        return DB::transaction(function () use ($userId, $cartItemId, $quantity) {
            $cart = $this->getCart($userId);
            $item = $cart->items()->findOrFail($cartItemId);

            if ($quantity <= 0) {
                $item->delete();
            } else {
                $variant = $item->variant()->lockForUpdate()->first();
                $this->assertVariantAvailable($variant, $quantity);
                $item->update(['quantity' => $quantity]);
            }

            if ($cart->coupon_code) {
                $this->revalidateCoupon($cart);
            }

            return $cart->fresh('items.variant.product.primaryImage');
        });
    }

    // ── Remove Item ───────────────────────────────────────────────────────────

    public function removeItem(string $userId, string $cartItemId): Cart
    {
        $cart = $this->getCart($userId);

        // Verifikasi kepemilikan item secara eksplisit sebelum menghapus.
        // findOrFail memastikan item ada DAN milik cart user ini (lewat relasi).
        $cart->items()->findOrFail($cartItemId)->delete();

        return $cart->fresh('items.variant.product.primaryImage');
    }

    // ── Apply Coupon ──────────────────────────────────────────────────────────

    public function applyCoupon(string $userId, string $code): Cart
    {
        $cart    = $this->getCart($userId);
        $user    = $cart->user;
        $subtotal = $cart->subtotal;

        if ($cart->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => ['Keranjang kosong.'],
            ]);
        }

        $result = $this->couponService->validate($code, $subtotal, $user);

        $cart->update([
            'coupon_code'     => $code,
            'coupon_discount' => $result['discount'],
        ]);

        return $cart->fresh('items.variant.product.primaryImage');
    }

    public function removeCoupon(string $userId): Cart
    {
        $cart = $this->getCart($userId);
        $cart->update(['coupon_code' => null, 'coupon_discount' => 0]);

        return $cart->fresh('items.variant.product.primaryImage');
    }

    // ── Validate Cart (dipanggil sebelum checkout) ────────────────────────────

    public function validateForCheckout(Cart $cart, ?array $selectedItemIds = null): void
    {
        if ($cart->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => ['Keranjang belanja kosong.'],
            ]);
        }

        $itemsToValidate = $selectedItemIds 
            ? $cart->items->whereIn('id', $selectedItemIds)
            : $cart->items;

        if ($selectedItemIds && $itemsToValidate->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => ['Produk yang dipilih tidak ditemukan di keranjang.'],
            ]);
        }

        $errors = [];
        $userType = $cart->user->user_type->value ?? 'consumer';

        // Fix Deadlock Risk: Lock rows sequentially by ID
        $variantIds = $itemsToValidate->pluck('product_variant_id')->all();
        $variants = ProductVariant::with('product')
            ->whereIn('id', $variantIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $priceUpdated = false;

        foreach ($itemsToValidate as $item) {
            $variant = $variants->get($item->product_variant_id);

            if (! $variant || ! $variant->is_active || ! $variant->product->status->isPublic()) {
                $errors[] = "{$item->variant->product->name} — {$item->variant->name} tidak tersedia lagi.";
                continue;
            }

            if ($variant->stock < $item->quantity) {
                $errors[] = "{$variant->product->name} — {$variant->name}: stok tersisa {$variant->stock}.";
            }

            // Fix Price Manipulation: Sinkronisasi harga terbaru untuk menghindari stale price
            $currentPrice = $variant->getPriceForUserType($userType);
            if ($item->unit_price != $currentPrice) {
                $item->update(['unit_price' => $currentPrice]);
                // Update on memory untuk re-calculate yang proper di baris berikutnya
                $item->unit_price = $currentPrice;
                $priceUpdated = true;
            }
        }

        if ($priceUpdated) {
            // Jika ada harga yang terupdate otomatis, kita refresh subtotal dari cart
            $cart->refresh();
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages(['cart' => $errors]);
        }
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function assertVariantAvailable(ProductVariant $variant, int $qty): void
    {
        if (! $variant->is_active) {
            throw ValidationException::withMessages([
                'variant_id' => ["{$variant->name} tidak tersedia."],
            ]);
        }

        if ($variant->stock < $qty) {
            throw ValidationException::withMessages([
                'quantity' => ["Stok {$variant->name} tidak cukup. Tersisa: {$variant->stock}."],
            ]);
        }
    }

    private function revalidateCoupon(Cart $cart): void
    {
        try {
            $result = $this->couponService->validate(
                $cart->coupon_code,
                $cart->fresh()->subtotal,
                $cart->user,
            );
            $cart->update(['coupon_discount' => $result['discount']]);
        } catch (\Exception) {
            // Coupon tidak lagi valid — hapus dari cart
            $cart->update(['coupon_code' => null, 'coupon_discount' => 0]);
        }
    }
}
