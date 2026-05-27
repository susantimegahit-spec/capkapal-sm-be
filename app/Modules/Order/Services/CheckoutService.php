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
            $this->cartService->validateForCheckout($cart, $data->cart_item_ids); // throw jika ada masalah

            $selectedItems = $data->cart_item_ids 
                ? $cart->items->whereIn('id', $data->cart_item_ids)
                : $cart->items;

            // ── 2. Lock variant rows & deduct stock ───────────────────────────
            $this->reserveStock($selectedItems);

            // ── 3. Hitung ongkir ──────────────────────────────────────────────
            $totalWeight = $selectedItems->sum(fn($item) =>
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

            $subtotalCheckout = $selectedItems->sum('subtotal');

            if ($data->coupon_code) {
                $couponResult  = $this->couponService->validate(
                    $data->coupon_code,
                    $subtotalCheckout,
                    $cart->user,
                );
                $couponDiscount = $couponResult['discount'];
            }

            // ── 5. Hitung nilai diskon reward points ─────────────────────────────
            // Kalkulasi nilai poin secara lokal (tidak baca DB di sini).
            // Validasi saldo dan pengurangan dilakukan secara atomic di step 12 (redeem()),
            // yang sudah menggunakan lockForUpdate() untuk mencegah double-spend.
            $pointsDiscount = $data->points_used > 0
                ? $data->points_used * RewardService::POINTS_VALUE
                : 0;

            // ── 6. Hitung total akhir ─────────────────────────────────────────
            $subtotal     = $subtotalCheckout;
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
            foreach ($selectedItems as $cartItem) {
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
                'village'        => $data->village,
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

            // ── 13. Buat Payment Intent di Midtrans ────────────────────────────
            $payment = $this->paymentService->createPaymentForOrder(
                order    : $order,
                method   : $data->payment_method,
                provider : $data->payment_provider,
            );

            // ── 14. Hapus item yang dicheckout dari cart ──────────────────────
            $this->cartService->cartRepository->removeItems($cart, $selectedItems->pluck('id')->all());

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
    private function reserveStock(iterable $selectedItems): void
    {
        $variantIds = [];
        foreach ($selectedItems as $item) {
            $variantIds[] = $item->product_variant_id;
        }

        // Lock semua variant sekaligus secara berurutan berdasarkan ID untuk mencegah deadlock
        $variants = ProductVariant::whereIn('id', $variantIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $errors = [];

        foreach ($selectedItems as $item) {
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
        foreach ($selectedItems as $item) {
            ProductVariant::where('id', $item->product_variant_id)
                ->decrement('stock', $item->quantity);
        }
    }
}
