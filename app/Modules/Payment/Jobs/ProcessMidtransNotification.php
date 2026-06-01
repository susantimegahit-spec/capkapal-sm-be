<?php

namespace App\Modules\Payment\Jobs;

use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Events\{PaymentFailed, PaymentSucceeded};
use App\Modules\Payment\Models\{Payment, PaymentLog};
use App\Modules\Reward\Models\PointTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\{DB, Log};

class ProcessMidtransNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int    $tries    = 3;
    public int    $backoff  = 10; // detik antar retry

    public function __construct(private readonly string $logId) {}

    public function handle(): void
    {
        $log = PaymentLog::findOrFail($this->logId);

        // Double-check idempotency: jangan proses ulang yang sudah selesai
        if ($log->status === 'processed') {
            return;
        }

        DB::transaction(function () use ($log) {
            try {
                $this->process($log);
                $log->update(['status' => 'processed', 'processed_at' => now()]);
            } catch (\Throwable $e) {
                $log->update([
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                    'processed_at'  => now(),
                ]);

                Log::error('Webhook processing failed', [
                    'log_id' => $this->logId,
                    'error'  => $e->getMessage(),
                    'trace'  => $e->getTraceAsString(),
                ]);

                throw $e; // Re-throw agar job di-retry
            }
        });
    }

    private function process(PaymentLog $log): void
    {
        // 1. Ambil payload
        $payload = $log->payload;

        // 2. Jika ternyata masih berupa string (karena lupa cast), decode secara manual
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }

        // 3. Jika payload kosong atau gagal decode
        if (!is_array($payload)) {
            Log::error('Invalid payload format in PaymentLog', ['log_id' => $log->id]);
            throw new \RuntimeException('Invalid payload format');
        }

        $orderId = $this->extractOrderId($payload);

        // Cari payment berdasarkan midtrans_order_id dengan penguncian baris (row lock)
        // untuk mencegah race condition / double processing.
        $payment = Payment::where('midtrans_order_id', $orderId)->lockForUpdate()->first();

        if (! $payment) {
            Log::warning('Payment not found for webhook', ['midtrans_order_id' => $orderId]);
            return; // Bukan payment kita — abaikan
        }

        // Link log ke payment
        $log->update(['payment_id' => $payment->id]);

        // Skip jika sudah final (idempotency layer ke-2)
        if ($payment->status->isFinal()) {
            Log::info('Payment already final, skipping', ['payment_id' => $payment->id]);
            return;
        }

        $midtransStatus = $this->extractStatus($payload);
        $newStatus      = $this->mapMidtransStatus($midtransStatus, $payload);

        $this->updatePayment($payment, $newStatus, $payload);
        $this->updateOrder($payment, $newStatus);
        $this->dispatchEvents($payment, $newStatus);
    }

    private function updatePayment(Payment $payment, PaymentStatus $newStatus, array $payload): void
    {
        $payment->update([
            'status'                  => $newStatus,
            'paid_at'                 => $newStatus->isSuccessful() ? now() : null,
            'midtrans_transaction_id' => $payload['transaction_id'] ?? $payment->midtrans_transaction_id,
            'gateway_metadata'        => array_merge($payment->gateway_metadata ?? [], $payload),
        ]);
    }

    private function updateOrder(Payment $payment, PaymentStatus $newStatus): void
    {
        $order = $payment->order;

        if ($newStatus->isSuccessful()) {
            // 1 Poin = Rp 1.000 dari belanja produk (sebelum ongkir)
            $pointsEarned = (int) floor($order->subtotal / 1000);

            if ($pointsEarned > 0 && $order->user_id) {
                $user = $order->user;
                if ($user) {
                    $user->increment('points', $pointsEarned);

                    PointTransaction::create([
                        'user_id'        => $user->id,
                        'amount'         => $pointsEarned,
                        'type'           => 'EARN',
                        'description'    => "Poin dari transaksi pesanan #{$order->order_number}",
                        'reference_type' => get_class($order),
                        'reference_id'   => $order->id,
                    ]);
                }
            }

            // Simpan jumlah poin yang didapat ke order
            $order->update(['points_earned' => $pointsEarned]);

            $order->transitionTo(
                next  : OrderStatus::Confirmed,
                notes : 'Pembayaran diterima dari Midtrans.',
            );
        } elseif ($newStatus === PaymentStatus::Failed || $newStatus === PaymentStatus::Expired) {
            // Kembalikan stok jika payment gagal/expired
            // Urutkan berdasarkan product_variant_id secara deterministik untuk mencegah deadlock
            $items = $order->items->sortBy('product_variant_id');
            foreach ($items as $item) {
                \App\Modules\Product\Models\ProductVariant::where('id', $item->product_variant_id)
                    ->lockForUpdate()
                    ->first()
                    ?->increment('stock', $item->quantity);
            }

            // Kembalikan poin setelah transaksi commit agar job tidak berjalan
            // jika transaksi di-rollback (mencegah double-refund)
            if ($order->points_used > 0) {
                DB::afterCommit(function () use ($order) {
                    \App\Jobs\RefundRewardPoints::dispatch($order->user_id, $order->points_used, $order->id)
                        ->onQueue('high');
                });
            }

            $order->transitionTo(
                next  : OrderStatus::Cancelled,
                notes : "Pembayaran {$newStatus->value}.",
            );
        }
    }

    private function dispatchEvents(Payment $payment, PaymentStatus $newStatus): void
    {
        if ($newStatus->isSuccessful()) {
            event(new PaymentSucceeded($payment));
        } elseif (in_array($newStatus, [PaymentStatus::Failed, PaymentStatus::Expired])) {
            event(new PaymentFailed($payment));
        }
    }

    private function mapMidtransStatus(string $transactionStatus, array $payload): PaymentStatus
    {
        $fraudStatus = $payload['fraud_status'] ?? 'accept';

        return match (strtolower($transactionStatus)) {
            'capture' => $fraudStatus === 'accept'
                ? PaymentStatus::Paid
                : PaymentStatus::Failed,
            'settlement'            => PaymentStatus::Paid,
            'deny', 'cancel'        => PaymentStatus::Failed,
            'expire'                => PaymentStatus::Expired,
            'pending'               => PaymentStatus::Waiting,
            'refund', 'partial_refund' => PaymentStatus::Refunded,
            default                 => PaymentStatus::Waiting,
        };
    }

    private function extractOrderId(array $payload): string
    {
        return $payload['order_id']
            ?? throw new \RuntimeException('Cannot extract order_id from Midtrans payload');
    }

    private function extractStatus(array $payload): string
    {
        return $payload['transaction_status'] ?? 'unknown';
    }

    public function failed(\Throwable $e): void
    {
        Log::critical('ProcessMidtransNotification job permanently failed', [
            'log_id' => $this->logId,
            'error'  => $e->getMessage(),
        ]);

        // Notifikasi tim ops via Slack/PagerDuty (implementasi sesuai kebutuhan)
        // SlackAlert::send("Payment webhook failed: {$this->logId}");
    }
}
