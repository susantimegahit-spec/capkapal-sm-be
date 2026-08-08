<?php

namespace App\Modules\Payment\Services;

use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Models\Order;
use App\Modules\Payment\Enums\{PaymentMethod, PaymentStatus};
use App\Modules\Payment\Gateway\Contracts\PaymentGatewayInterface;
use App\Modules\Payment\Models\Payment;
use Illuminate\Support\Facades\{DB, Log};
use Midtrans\Config as MidtransConfig;
use Midtrans\Transaction;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
    ) {}

    /**
     * Dipanggil dari CheckoutService di dalam DB::transaction().
     * Membuat payment record + call Midtrans Core API.
     */
    public function createPaymentForOrder(
        Order   $order,
        string  $method,
        ?string $provider = null
    ): array {
        $paymentMethod = PaymentMethod::from($method);

        // Manual QRIS: buat payment record tanpa memanggil gateway API
        if ($paymentMethod === PaymentMethod::ManualQris) {
            // Generate kode unik 3 digit (100-999) untuk memudahkan verifikasi admin
            $uniqueCode = random_int(100, 999);

            $payment = Payment::create([
                'order_id'           => $order->id,
                'midtrans_order_id'  => null,
                'method'             => $paymentMethod,
                'provider'           => 'manual',
                'status'             => PaymentStatus::Waiting,
                'amount'             => $order->total,
                'unique_code'        => $uniqueCode,
                'currency'           => 'IDR',
                'expired_at'         => now()->addDay(),
                'gateway_metadata'   => ['type' => 'manual_qris'],
            ]);

            return $payment->instructions;
        }

        // Panggil Midtrans sesuai metode
        $gatewayResult = match ($paymentMethod) {
            PaymentMethod::VirtualAccount => $this->gateway->createVirtualAccount($order, $provider),
            PaymentMethod::Ewallet        => $this->gateway->createEwallet(
                $order,
                $provider,
                config('app.frontend_url') . '/order/' . $order->order_number
            ),
            PaymentMethod::Qris           => $this->gateway->createQris($order),
            PaymentMethod::RetailOutlet   => $this->gateway->createRetailOutlet($order, $provider),
            PaymentMethod::Flat           => $this->gateway->createFlatTesting($order, $provider),
            PaymentMethod::CreditCard     => throw new \RuntimeException('Credit card belum diimplementasi.'),
            default                       => throw new \RuntimeException("Metode pembayaran {$method} tidak didukung."),
        };

        // Simpan payment record
        $payment = Payment::create([
            'order_id'                => $order->id,
            'midtrans_order_id'       => $gatewayResult['midtrans_order_id'],
            'method'                  => $paymentMethod,
            'provider'                => $provider,
            'status'                  => PaymentStatus::Waiting,
            'amount'                  => $order->total,
            'currency'                => 'IDR',
            'payment_code'            => $gatewayResult['payment_code']  ?? null,
            'payment_url'             => $gatewayResult['payment_url']   ?? null,
            'qr_string'               => $gatewayResult['qr_string']     ?? null,
            'expired_at'              => $gatewayResult['expired_at']
                ? \Carbon\Carbon::parse($gatewayResult['expired_at'])
                : now()->addDay(),
            'gateway_metadata'        => $gatewayResult['metadata']      ?? null,
        ]);

        return $payment->instructions;
    }

    /**
     * Cek status pembayaran (untuk polling dari frontend).
     */
    public function checkStatus(string $orderId, string $userId): array
    {
        $payment = Payment::whereHas(
            'order',
            fn($q) =>
            $q->where('id', $orderId)->where('user_id', $userId)
        )->firstOrFail();

        if ($payment->status->isFinal()) {
            return [
                'status'      => $payment->status->value,
                'paid_at'     => $payment->paid_at?->toISOString(),
                'instructions' => null,
            ];
        }

        // Check expired
        if ($payment->isExpired()) {
            $payment->update(['status' => PaymentStatus::Expired]);
            return ['status' => 'expired', 'instructions' => null];
        }

        return [
            'status'       => $payment->status->value,
            'instructions' => $payment->instructions,
            'expired_at'   => $payment->expired_at?->toISOString(),
        ];
    }

    /**
     * Buat ulang payment untuk order yang expired/failed.
     */
    public function retryPayment(string $orderNumber, string $userId, string $method, ?string $provider): array
    {
        return DB::transaction(function () use ($orderNumber, $userId, $method, $provider) {
            $order = Order::where('order_number', $orderNumber)
                ->where('user_id', $userId)
                ->with('payment')
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($order->status->value, ['pending'])) {
                throw new \DomainException('Order tidak bisa diretry.');
            }

            // Hapus payment lama di dalam transaksi — jika Midtrans gagal,
            // transaksi di-rollback dan record lama tetap ada di DB.
            $order->payment?->delete();

            return $this->createPaymentForOrder($order, $method, $provider);
        });
    }

    /**
     * Sync semua pembayaran yang masih waiting secara massal
     */
    public function syncAllPendingPayments(): array
    {
        // Ambil semua payment Waiting KECUALI manual_qris (yang diverifikasi oleh admin)
        $payments = Payment::where('status', PaymentStatus::Waiting)
            ->where('method', '!=', PaymentMethod::ManualQris->value)
            ->with(['order.items.variant'])
            ->get();

        $serverKey = config('services.midtrans.server_key');

        if (empty($serverKey)) {
            throw new \Exception('Midtrans Server Key belum dikonfigurasi di file .env');
        }

        // Configure Midtrans for status checks
        MidtransConfig::$serverKey    = $serverKey;
        MidtransConfig::$isProduction = (bool) config('services.midtrans.is_production', false);

        $syncedCount = 0;
        $failedCount = 0;
        $unchangedCount = 0;

        foreach ($payments as $payment) {
            try {
                $midtransStatus = null;

                // ── A. Cek status via Midtrans API ──
                if ($payment->midtrans_order_id) {
                    try {
                        $response = Transaction::status($payment->midtrans_order_id);
                        $transactionStatus = $response->transaction_status ?? null;
                        $fraudStatus       = $response->fraud_status ?? 'accept';

                        if (in_array($transactionStatus, ['settlement', 'capture']) && $fraudStatus === 'accept') {
                            $midtransStatus = 'PAID';
                        } elseif (in_array($transactionStatus, ['deny', 'cancel'])) {
                            $midtransStatus = 'FAILED';
                        } elseif ($transactionStatus === 'expire') {
                            $midtransStatus = 'EXPIRED';
                        }
                    } catch (\Exception $e) {
                        // Jika gagal hit API, cek berdasarkan waktu expired
                        if ($payment->expired_at && $payment->expired_at->isPast()) {
                            $midtransStatus = 'EXPIRED';
                        } else {
                            $failedCount++;
                            continue;
                        }
                    }
                }
                // ── B. Fallback: Cek waktu expired ──
                else {
                    if ($payment->expired_at && $payment->expired_at->isPast()) {
                        $midtransStatus = 'EXPIRED';
                    }
                }

                // Jika status masih null (belum kedaluwarsa & belum dibayar), abaikan
                if ($midtransStatus === null) {
                    $unchangedCount++;
                    continue;
                }

                // ── 3. Terapkan logika perubahan database ──
                DB::transaction(function () use ($payment, $midtransStatus, &$syncedCount) {
                    $order = $payment->order;

                    // Kasus 1: Kadaluwarsa atau Gagal
                    if (in_array($midtransStatus, ['EXPIRED', 'FAILED'])) {
                        $payment->update(['status' => $midtransStatus === 'EXPIRED'
                            ? PaymentStatus::Expired
                            : PaymentStatus::Failed,
                        ]);

                        // Kembalikan stok: urutkan deterministik, lock per baris
                        $items = $order->items->sortBy('product_variant_id');
                        foreach ($items as $item) {
                            if ($item->variant) {
                                \App\Modules\Product\Models\ProductVariant::where('id', $item->product_variant_id)
                                    ->lockForUpdate()
                                    ->first()
                                    ?->increment('stock', $item->quantity);
                            }
                        }

                        $order->transitionTo(
                            OrderStatus::Cancelled,
                            'Dibatalkan sistem (Sync Massal): Waktu pembayaran kedaluwarsa.'
                        );

                        $syncedCount++;
                    }
                    // Kasus 2: Sudah Dibayar
                    elseif ($midtransStatus === 'PAID') {
                        $payment->update([
                            'status'  => PaymentStatus::Paid,
                            'paid_at' => now(),
                        ]);

                        // Tambahkan poin: 1 Poin = Rp 1.000 (sebelum ongkir)
                        $pointsEarned = (int) floor($order->subtotal / 1000);

                        if ($pointsEarned > 0 && $order->user_id) {
                            $user = $order->user;
                            if ($user) {
                                $user->increment('points', $pointsEarned);

                                \App\Modules\Reward\Models\PointTransaction::create([
                                    'user_id'        => $user->id,
                                    'amount'         => $pointsEarned,
                                    'type'           => 'EARN',
                                    'description'    => "Poin dari transaksi pesanan #{$order->order_number}",
                                    'reference_type' => get_class($order),
                                    'reference_id'   => $order->id,
                                ]);
                            }
                        }

                        $order->update(['points_earned' => $pointsEarned]);

                        $order->transitionTo(
                            OrderStatus::Confirmed,
                            'Pembayaran terkonfirmasi (Sync Manual via Midtrans).'
                        );

                        $syncedCount++;
                    }
                });
            } catch (\Exception $e) {
                \Log::error("Gagal sync payment massal ID: {$payment->id}", [
                    'error' => $e->getMessage()
                ]);
                $failedCount++;
            }
        }

        return [
            'total_processed' => $payments->count(),
            'synced'          => $syncedCount,
            'unchanged'       => $unchangedCount,
            'failed'          => $failedCount,
        ];
    }
}
