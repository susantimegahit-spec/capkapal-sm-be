<?php

namespace App\Modules\Order\Services;

use App\Modules\Order\DataTransferObjects\CheckoutData;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Events\OrderPlaced;
use App\Modules\Order\Models\{Cart, Order, OrderItem};
use App\Modules\Order\Repositories\Contracts\OrderRepositoryInterface;
use App\Modules\Payment\Services\PaymentService;
use App\Modules\Product\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        private readonly CartService             $cartService,
        private readonly CouponService           $couponService,
        private readonly ShippingService         $shippingService,
        private readonly RewardService           $rewardService,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly PaymentService $paymentService,
    ) {}

    /**
     * Entry point checkout.
     * Semua operasi berjalan di dalam 1 DB transaction.
     * Jika satu langkah gagal, seluruhnya di-rollback.
     */
    public function process(string $userId, CheckoutData $data): array
    {
        return DB::transaction(function () use ($userId, $data) {

            // ── 1. Ambil dan validasi cart ────────────────────────────────────
            $cart = $this->cartService->getCart($userId);
            $this->cartService->validateForCheckout($cart); // throw jika ada masalah

            // ── 2. Lock variant rows & deduct stock ───────────────────────────
            $this->reserveStock($cart);

            // ── 3. Hitung ongkir ──────────────────────────────────────────────
            $totalWeight = $cart->items->sum(fn($item) =>
                ($item->variant->weight ?? 100) * $item->quantity
            );

            $shipping = $this->shippingService->calculate(
                courier        : $data->shipping_courier,
                service        : $data->shipping_service,
                destinationCity: $data->city,
                totalWeightGram: $totalWeight,
            );

            // ── 4. Hitung diskon kupon ────────────────────────────────────────
            $couponResult  = null;
            $couponDiscount = 0;

            if ($data->coupon_code) {
                $couponResult  = $this->couponService->validate(
                    $data->coupon_code,
                    $cart->subtotal,
                    $cart->user,
                );
                $couponDiscount = $couponResult['discount'];
            }

            // ── 5. Hitung diskon reward points ────────────────────────────────
            $pointsDiscount = 0;

            if ($data->points_used > 0) {
                $pointsDiscount = $this->rewardService->calculatePointsValue(
                    userId     : $userId,
                    pointsToUse: $data->points_used,
                );
            }

            // ── 6. Hitung total akhir ─────────────────────────────────────────
            $subtotal     = $cart->subtotal;
            $shippingFee  = $shipping['price'];
            $total        = max(0, $subtotal - $couponDiscount - $pointsDiscount + $shippingFee);

            // ── 7. Buat Order ─────────────────────────────────────────────────
            $order = $this->orderRepository->create([
                'user_id'          => $userId,
                'order_number'     => $this->orderRepository->generateOrderNumber(),
                'status'           => OrderStatus::Pending,
                'subtotal'         => $subtotal,
                'coupon_discount'  => $couponDiscount,
                'points_discount'  => $pointsDiscount,
                'shipping_fee'     => $shippingFee,
                'total'            => $total,
                'coupon_code'      => $data->coupon_code,
                'points_used'      => $data->points_used,
                'shipping_courier' => $data->shipping_courier,
                'shipping_service' => $data->shipping_service,
                'estimated_delivery' => $shipping['estimated_delivery'],
                'customer_notes'   => $data->customer_notes,
            ]);

            // ── 8. Buat Order Items (dengan snapshot) ─────────────────────────
            foreach ($cart->items as $cartItem) {
                $variant = $cartItem->variant;

                OrderItem::create([
                    'order_id'            => $order->id,
                    'product_variant_id'  => $variant->id,
                    'product_name'        => $variant->product->name,
                    'variant_name'        => $variant->name,
                    'sku'                 => $variant->sku,
                    'unit_price'          => $cartItem->unit_price,
                    'quantity'            => $cartItem->quantity,
                    'subtotal'            => $cartItem->subtotal,
                    'weight'              => $variant->weight,
                    'product_snapshot'    => OrderItem::buildSnapshot($variant),
                ]);
            }

            // ── 9. Simpan Shipping Address ────────────────────────────────────
            $order->shippingAddress()->create([
                'recipient_name' => $data->recipient_name,
                'phone'          => $data->phone,
                'address'        => $data->address,
                'district'       => $data->district,
                'city'           => $data->city,
                'province'       => $data->province,
                'postal_code'    => $data->postal_code,
            ]);

            // ── 10. Catat status history awal ─────────────────────────────────
            $order->statusHistories()->create([
                'status'     => OrderStatus::Pending->value,
                'notes'      => 'Order dibuat, menunggu pembayaran.',
                'created_by' => $userId,
            ]);

            // ── 11. Tandai coupon digunakan ───────────────────────────────────
            if ($couponResult) {
                $this->couponService->markUsed(
                    $couponResult['coupon']->id,
                    $userId,
                    $order->id,
                    $couponDiscount,
                );
            }

            // ── 12. Deduct reward points (jika digunakan) ─────────────────────
            if ($data->points_used > 0) {
                $this->rewardService->redeem(
                    userId  : $userId,
                    points  : $data->points_used,
                    orderId : $order->id,
                );
            }

            // ── 13. Buat Payment Intent di Xendit ────────────────────────────
            $payment = $this->paymentService->createPaymentForOrder(
                order    : $order,
                method   : $data->payment_method,
                provider : $data->payment_provider,
            );

            // ── 14. Kosongkan cart ────────────────────────────────────────────
            $this->cartService->cartRepository->clearCart($cart);

            // ── 15. Dispatch event (email konfirmasi, dll.) ───────────────────
            event(new OrderPlaced($order));

            // ── Kembalikan hasil ke controller ────────────────────────────────
            return [
                'order'   => $order->load(['items', 'shippingAddress']),
                'payment' => $payment,
            ];
        });
    }

    /**
     * Lock semua variant yang ada di cart dan kurangi stoknya secara atomic.
     * Menggunakan SELECT FOR UPDATE untuk mencegah race condition.
     */
    private function reserveStock(Cart $cart): void
    {
        $variantIds = $cart->items->pluck('product_variant_id')->all();

        // Lock semua variant sekaligus (1 query, bukan N queries)
        $variants = ProductVariant::whereIn('id', $variantIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $errors = [];

        foreach ($cart->items as $item) {
            $variant = $variants->get($item->product_variant_id);

            if (! $variant || $variant->stock < $item->quantity) {
                $name    = $variant?->name ?? 'Produk';
                $tersisa = $variant?->stock ?? 0;
                $errors[] = "{$name}: stok tersisa {$tersisa}, Anda memesan {$item->quantity}.";
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages(['stock' => $errors]);
        }

        // Kurangi stok semua variant
        foreach ($cart->items as $item) {
            ProductVariant::where('id', $item->product_variant_id)
                ->decrement('stock', $item->quantity);
        }
    }
}
